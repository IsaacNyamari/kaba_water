<?php

namespace App\Services\Prepaid;

use App\Models\Client;
use App\Models\Integration;
use App\Models\Meter;
use App\Models\MpesaTransaction;
use App\Models\PrepaidToken;
use App\Models\Tariff;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\PrepaidTokenSold;
use App\Services\Payments\PaymentAllocator;
use App\Services\Sms\SmsService;
use App\Support\Money\MoneyMath;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Throwable;

/**
 * Sells prepaid water: takes a payment for a prepaid meter, first deducts
 * the configured share for arrears (unpaid invoices — water, garbage,
 * service charge), buys units with the rest at the active tariff, gets an
 * STS token from the vending system and texts it to the customer.
 */
class PrepaidVending
{
    public function __construct(
        protected PaymentAllocator $allocator,
        protected TokenVendorFactory $vendors,
        protected LedgerService $ledger,
        protected SmsService $sms,
    ) {}

    /**
     * @param  bool  $fromDeposit  the money is already held as a customer deposit (suspense / failed earlier vend)
     *
     * @throws TokenVendingException when the vendor has no vending set up (nothing is changed)
     */
    public function sell(
        Meter $meter,
        string $amount,
        ?MpesaTransaction $transaction = null,
        ?int $issuedBy = null,
        bool $fromDeposit = false,
        ?string $notifyPhone = null,
        bool $deductArrears = true,
    ): PrepaidToken {
        $integration = Integration::active($meter->vendor_id, Integration::TOKEN_VENDING)
            ?? throw new TokenVendingException('Prepaid token vending is not switched on for this workspace.');

        $vendor = $this->vendors->for($integration);
        $client = $meter->client_id ? Client::withoutGlobalScope('vendor')->find($meter->client_id) : null;

        $deducted = '0.00';
        if ($deductArrears && $client && ($percent = (string) $integration->setting('arrears_percent', '0')) && MoneyMath::isPositive($percent)) {
            $cap = MoneyMath::multiply($amount, (string) BigDecimal::of($percent)->dividedBy(100, 4, RoundingMode::HalfUp));
            $owed = $this->allocator->outstanding($client);
            $target = MoneyMath::compare($cap, $owed) < 0 ? $cap : $owed;

            if (MoneyMath::isPositive($target)) {
                $deducted = $this->allocator->allocate($client, $target, $transaction, fromDeposit: $fromDeposit, recordedBy: $issuedBy);
            }
        }

        $tokenAmount = MoneyMath::sub($amount, $deducted);
        $units = $this->unitsFor($meter->vendor_id, $tokenAmount, $integration);

        $token = PrepaidToken::withoutGlobalScope('vendor')->create([
            'vendor_id' => $meter->vendor_id,
            'meter_id' => $meter->id,
            'client_id' => $client?->id,
            'mpesa_transaction_id' => $transaction?->id,
            'amount_paid' => MoneyMath::str($amount),
            'arrears_deducted' => $deducted,
            'token_amount' => $tokenAmount,
            'units' => $units,
            'provider' => (string) $integration->setting('driver'),
            'status' => 'pending',
            'issued_by' => $issuedBy,
        ]);

        if ($transaction && MoneyMath::isPositive($deducted)) {
            $transaction->increment('allocated_amount', $deducted);
        }

        if (! MoneyMath::isPositive($tokenAmount)) {
            $token->update(['status' => 'failed', 'error' => 'The whole payment went to arrears; no water was bought.']);
            $this->notify($token, $client, $notifyPhone ?? $transaction?->phone);

            return $token;
        }

        try {
            $result = $vendor->vend($meter, $tokenAmount, $units, 'T'.$token->id.($transaction?->receipt ? '-'.$transaction->receipt : ''));
        } catch (Throwable $e) {
            $token->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);

            return $token;
        }

        $token->update([
            'token' => $result->token,
            'units' => $result->units ?? $units,
            'provider_reference' => $result->reference,
            'status' => 'issued',
        ]);

        $this->ledger->postEvent(new PrepaidTokenSold($token->fresh(['transaction', 'meter']), $fromDeposit));

        if ($transaction) {
            $transaction->increment('allocated_amount', $tokenAmount);
        }

        $this->notify($token->fresh(), $client, $notifyPhone ?? $transaction?->phone);

        return $token;
    }

    /**
     * Water units (m³) an amount buys at the vendor's tariff. A tiered
     * tariff is read band by band from zero, so the first units are the
     * cheapest — the same as a postpaid bill for that consumption.
     */
    public function unitsFor(int $vendorId, string $amount, ?Integration $integration = null): string
    {
        $tariff = Tariff::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->when($integration?->setting('tariff_id'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('is_active', true))
            ->first();

        if (! $tariff) {
            return '0.000';
        }

        $money = BigDecimal::of(MoneyMath::str($amount));

        if ($tariff->type === 'flat') {
            $rate = BigDecimal::of((string) $tariff->rate);

            return $rate->isPositive() ? (string) $money->dividedBy($rate, 3, RoundingMode::Down) : '0.000';
        }

        $units = BigDecimal::zero();
        $last = BigDecimal::zero();

        foreach (($tariff->tiers ?? []) as $tier) {
            $rate = BigDecimal::of((string) $tier['rate']);
            if (! $rate->isPositive()) {
                continue;
            }

            $upTo = isset($tier['up_to']) && $tier['up_to'] !== null ? BigDecimal::of((string) $tier['up_to']) : null;
            $band = $upTo?->minus($last);
            $bandCost = $band?->multipliedBy($rate);

            if ($bandCost === null || $money->isLessThanOrEqualTo($bandCost)) {
                $units = $units->plus($money->dividedBy($rate, 3, RoundingMode::Down));
                $money = BigDecimal::zero();
                break;
            }

            $units = $units->plus($band);
            $money = $money->minus($bandCost);
            $last = $upTo;
        }

        return (string) $units->toScale(3, RoundingMode::Down);
    }

    protected function notify(PrepaidToken $token, ?Client $client, ?string $phone): void
    {
        $phone = $phone ?: $client?->phone;
        $meter = $token->meter ?? Meter::withoutGlobalScope('vendor')->find($token->meter_id);

        $text = $token->status === 'issued'
            ? "Water token for meter {$meter?->serial}: {$token->formattedToken()}. Units: {$token->units} m3. Paid KES ".MoneyMath::format((string) $token->amount_paid)
                .(MoneyMath::isPositive((string) $token->arrears_deducted) ? ', arrears KES '.MoneyMath::format((string) $token->arrears_deducted) : '').'.'
            : 'We received KES '.MoneyMath::format((string) $token->amount_paid)." for meter {$meter?->serial}. It cleared arrears of KES ".MoneyMath::format((string) $token->arrears_deducted).'; no token was issued.';

        $this->sms->send($token->vendor_id, $phone, $text, 'prepaid_token', $client);

        if ($token->status === 'issued') {
            $token->update(['sms_sent_at' => now()]);
        }
    }
}
