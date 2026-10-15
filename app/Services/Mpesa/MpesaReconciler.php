<?php

namespace App\Services\Mpesa;

use App\Models\Client;
use App\Models\Integration;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\MpesaTransaction;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\MpesaDepositReceived;
use App\Services\Payments\PaymentAllocator;
use App\Services\Prepaid\PrepaidVending;
use App\Services\Sms\SmsService;
use App\Support\Money\MoneyMath;
use App\Support\Notifier;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns M-Pesa callbacks into reconciled payments.
 *
 *  1. Record the transaction exactly once (keyed on the M-Pesa receipt).
 *  2. Work out who paid from the account reference: invoice number, meter
 *     number, account number or house number (then a unique phone match).
 *  3. Prepaid meter → sell a token. Otherwise settle the client's unpaid
 *     invoices, oldest first.
 *  4. Anything left over is held as a customer deposit — "credit" when the
 *     client is known, "suspense" when not — for the accountant to apply.
 *  5. Text the customer a confirmation.
 */
class MpesaReconciler
{
    public function __construct(
        protected PaymentAllocator $allocator,
        protected PrepaidVending $prepaid,
        protected LedgerService $ledger,
        protected SmsService $sms,
    ) {}

    /** Safaricom C2B confirmation (Paybill / Till payment made from the customer's phone). */
    public function recordC2b(Integration $integration, array $payload): ?MpesaTransaction
    {
        $receipt = strtoupper(trim((string) ($payload['TransID'] ?? '')));

        if ($receipt === '' || MpesaTransaction::withoutGlobalScope('vendor')->where('receipt', $receipt)->exists()) {
            return null;
        }

        $transaction = MpesaTransaction::withoutGlobalScope('vendor')->create([
            'vendor_id' => $integration->vendor_id,
            'channel' => 'c2b',
            'receipt' => $receipt,
            'phone' => (string) ($payload['MSISDN'] ?? ''),
            'payer_name' => trim(implode(' ', array_filter([$payload['FirstName'] ?? null, $payload['MiddleName'] ?? null, $payload['LastName'] ?? null]))) ?: null,
            'amount' => MoneyMath::str((string) ($payload['TransAmount'] ?? '0')),
            'account_reference' => trim((string) ($payload['BillRefNumber'] ?? '')) ?: null,
            'short_code' => (string) ($payload['BusinessShortCode'] ?? ''),
            'status' => 'suspense',
            'payload' => $payload,
            'transacted_at' => self::mpesaTime($payload['TransTime'] ?? null),
        ]);

        $this->apply($transaction);

        return $transaction;
    }

    /** Safaricom STK push result (the customer entered — or refused — their PIN). */
    public function recordStkCallback(Integration $integration, array $payload): ?MpesaTransaction
    {
        $callback = $payload['Body']['stkCallback'] ?? [];
        $checkoutId = (string) ($callback['CheckoutRequestID'] ?? '');

        $transaction = MpesaTransaction::withoutGlobalScope('vendor')
            ->where('vendor_id', $integration->vendor_id)
            ->where('checkout_request_id', $checkoutId)
            ->first();

        if (! $transaction || $transaction->status !== 'pending') {
            return $transaction;
        }

        $resultCode = (string) ($callback['ResultCode'] ?? '');
        $transaction->fill([
            'result_code' => $resultCode,
            'result_description' => mb_substr((string) ($callback['ResultDesc'] ?? ''), 0, 250),
            'payload' => $payload,
        ]);

        if ($resultCode !== '0') {
            $transaction->fill(['status' => 'failed'])->save();

            return $transaction;
        }

        $items = collect($callback['CallbackMetadata']['Item'] ?? [])->pluck('Value', 'Name');
        $receipt = strtoupper((string) $items->get('MpesaReceiptNumber'));

        // The same payment already arrived as a C2B confirmation — keep that one.
        if ($receipt && MpesaTransaction::withoutGlobalScope('vendor')->where('receipt', $receipt)->exists()) {
            $transaction->fill(['status' => 'failed', 'result_description' => "Recorded through the Paybill confirmation ({$receipt})."])->save();

            return $transaction;
        }

        $transaction->fill([
            'receipt' => $receipt ?: null,
            'amount' => MoneyMath::str((string) ($items->get('Amount') ?? $transaction->amount)),
            'phone' => (string) ($items->get('PhoneNumber') ?? $transaction->phone),
            'transacted_at' => self::mpesaTime($items->get('TransactionDate')),
            'status' => 'suspense',
        ])->save();

        $this->apply($transaction);

        return $transaction;
    }

    /**
     * Apply a received payment. Never throws: if anything goes wrong the
     * money stays in suspense with a note, and the team is told.
     */
    public function apply(MpesaTransaction $transaction): void
    {
        try {
            $this->resolvePayer($transaction);

            if ($transaction->meter_id && ($meter = Meter::withoutGlobalScope('vendor')->find($transaction->meter_id))?->isPrepaid()) {
                $transaction->update(['purpose' => 'token']);
                $token = $this->prepaid->sell($meter, (string) $transaction->amount, $transaction);
                $transaction->refresh();
                $this->holdRemainder($transaction);

                if ($token->status === 'failed') {
                    $this->alertTeam($transaction, 'Token not issued', "M-Pesa {$transaction->receipt} for meter {$meter->serial}: {$token->error}");
                }

                return;
            }

            if ($transaction->client_id) {
                $client = Client::withoutGlobalScope('vendor')->find($transaction->client_id);
                $preferred = $transaction->invoice_id ? Invoice::withoutGlobalScope('vendor')->find($transaction->invoice_id) : null;
                $applied = $this->allocator->allocate($client, (string) $transaction->amount, $transaction, $preferred);
                $transaction->update(['allocated_amount' => $applied]);
            }

            $this->holdRemainder($transaction);
            $this->confirm($transaction->refresh());
        } catch (Throwable $e) {
            report($e);
            $transaction->refresh();
            $transaction->update([
                'status' => $transaction->hasUnallocated() ? ($transaction->client_id ? 'credit' : 'suspense') : 'matched',
                'notes' => trim(($transaction->notes ? $transaction->notes."\n" : '').'Auto-reconciliation stopped: '.mb_substr($e->getMessage(), 0, 200)),
            ]);
            $this->alertTeam($transaction, 'M-Pesa payment needs attention', "{$transaction->receipt}: ".mb_substr($e->getMessage(), 0, 120));
        }
    }

    /**
     * Accountant applies a suspense / credit transaction to a client (or a
     * prepaid meter). The money is already in Customer Deposits, so the
     * payments it creates are settled from there.
     */
    public function assign(MpesaTransaction $transaction, ?Client $client, ?Meter $meter, int $userId): void
    {
        DB::transaction(function () use ($transaction, $client, $meter, $userId) {
            $transaction->update([
                'client_id' => $client?->id ?? $meter?->client_id ?? $transaction->client_id,
                'meter_id' => $meter?->id ?? $transaction->meter_id,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ]);
        });

        $amount = $transaction->unallocated();

        if ($meter?->isPrepaid()) {
            $transaction->update(['purpose' => 'token']);
            $this->prepaid->sell($meter, $amount, $transaction, $userId, fromDeposit: true);
        } elseif ($transaction->client_id) {
            $applied = $this->allocator->allocate(
                Client::withoutGlobalScope('vendor')->findOrFail($transaction->client_id),
                $amount,
                $transaction,
                fromDeposit: true,
                recordedBy: $userId,
            );
            $transaction->increment('allocated_amount', $applied);
        }

        $transaction->refresh();
        $this->settleStatus($transaction);
        $this->confirm($transaction);
    }

    /** Apply a client's held credit to invoices issued since (called when an invoice is sent). */
    public function applyCredits(Client $client): void
    {
        MpesaTransaction::withoutGlobalScope('vendor')
            ->where('vendor_id', $client->vendor_id)
            ->where('client_id', $client->id)
            ->where('status', 'credit')
            ->orderBy('transacted_at')
            ->get()
            ->each(function (MpesaTransaction $t) use ($client) {
                $applied = $this->allocator->allocate($client, $t->unallocated(), $t, fromDeposit: true);
                if (MoneyMath::isPositive($applied)) {
                    $t->increment('allocated_amount', $applied);
                    $this->settleStatus($t->refresh());
                }
            });
    }

    /** Find the client / meter / invoice the account reference points at. */
    public function resolvePayer(MpesaTransaction $transaction): void
    {
        if ($transaction->client_id || $transaction->meter_id) {
            return;
        }

        $vendorId = $transaction->vendor_id;
        $ref = strtoupper(preg_replace('/\s+/', '', (string) $transaction->account_reference) ?? '');
        $found = ['client_id' => null, 'meter_id' => null, 'invoice_id' => null, 'via' => null];

        if ($ref !== '') {
            if ($invoice = Invoice::withoutGlobalScope('vendor')->where('vendor_id', $vendorId)->whereRaw('UPPER(number) = ?', [$ref])->first()) {
                $found = ['client_id' => $invoice->client_id, 'meter_id' => null, 'invoice_id' => $invoice->id, 'via' => 'invoice number'];
            } elseif ($meter = Meter::withoutGlobalScope('vendor')->where('vendor_id', $vendorId)->whereRaw("UPPER(REPLACE(serial, ' ', '')) = ?", [$ref])->first()) {
                $found = ['client_id' => $meter->client_id, 'meter_id' => $meter->id, 'invoice_id' => null, 'via' => 'meter number'];
            } elseif ($client = Client::withoutGlobalScope('vendor')->where('vendor_id', $vendorId)->whereRaw('UPPER(account_no) = ?', [$ref])->first()) {
                $found = ['client_id' => $client->id, 'meter_id' => null, 'invoice_id' => null, 'via' => 'account number'];
            } else {
                $byHouse = Client::withoutGlobalScope('vendor')->where('vendor_id', $vendorId)
                    ->whereRaw("UPPER(REPLACE(house_number, ' ', '')) = ?", [$ref])->limit(2)->get();

                if ($byHouse->count() === 1) {
                    $found = ['client_id' => $byHouse->first()->id, 'meter_id' => null, 'invoice_id' => null, 'via' => 'house number'];
                }
            }
        }

        // Last resort: exactly one client registered with the paying phone number.
        if (! $found['client_id'] && ! $found['meter_id'] && $transaction->phone) {
            $local = PhoneNumber::normalize($transaction->phone);
            $byPhone = Client::withoutGlobalScope('vendor')->where('vendor_id', $vendorId)->whereNotNull('phone')->get(['id', 'phone'])
                ->filter(fn (Client $c) => PhoneNumber::normalize($c->phone) === $local);

            if ($byPhone->count() === 1) {
                $found = ['client_id' => $byPhone->first()->id, 'meter_id' => null, 'invoice_id' => null, 'via' => 'phone number'];
            }
        }

        if (! $found['client_id'] && ! $found['meter_id']) {
            return;
        }

        // A postpaid meter number pays that meter's client.
        if ($found['meter_id'] && ! Meter::withoutGlobalScope('vendor')->find($found['meter_id'])?->isPrepaid()) {
            $found['meter_id'] = null;
        }

        $transaction->update([
            'client_id' => $found['client_id'],
            'meter_id' => $found['meter_id'],
            'invoice_id' => $transaction->invoice_id ?? $found['invoice_id'],
            'notes' => trim(($transaction->notes ? $transaction->notes."\n" : '')."Matched by {$found['via']}."),
        ]);
    }

    /** Hold what is left of fresh money in Customer Deposits and set the status. */
    protected function holdRemainder(MpesaTransaction $transaction): void
    {
        if ($transaction->hasUnallocated()) {
            $this->ledger->postEvent(new MpesaDepositReceived($transaction, $transaction->unallocated()));
        }

        $this->settleStatus($transaction);

        if ($transaction->status === 'suspense') {
            $this->alertTeam($transaction, 'M-Pesa payment in suspense', "{$transaction->receipt} — KES ".MoneyMath::format((string) $transaction->amount)." from {$transaction->phone}, account \"{$transaction->account_reference}\" did not match a customer.");
        }
    }

    protected function settleStatus(MpesaTransaction $transaction): void
    {
        $status = match (true) {
            ! $transaction->hasUnallocated() => 'matched',
            (bool) $transaction->client_id => 'credit',
            default => 'suspense',
        };

        if ($transaction->status !== $status) {
            $transaction->update(['status' => $status]);
        }
    }

    /** SMS the payer that the payment was received and what it was applied to. */
    protected function confirm(MpesaTransaction $transaction): void
    {
        if ($transaction->purpose === 'token') {
            return; // The token SMS is the confirmation.
        }

        $client = $transaction->client_id ? Client::withoutGlobalScope('vendor')->find($transaction->client_id) : null;
        $amount = 'KES '.MoneyMath::format((string) $transaction->amount);

        if (! $client) {
            $text = "We received {$amount} (M-Pesa {$transaction->receipt}). The account \"{$transaction->account_reference}\" was not found; our team will apply it and contact you.";
        } else {
            $balance = $this->allocator->outstanding($client);
            $credit = $transaction->hasUnallocated() ? ' Credit held: KES '.MoneyMath::format($transaction->unallocated()).'.' : '';
            $text = "Dear {$client->name}, payment of {$amount} received (M-Pesa {$transaction->receipt}) for account {$client->account_no}. Balance due: KES ".MoneyMath::format($balance).".{$credit} Thank you.";
        }

        $this->sms->send($transaction->vendor_id, $transaction->phone ?: $client?->phone, $text, 'payment_confirmation', $client);
    }

    protected function alertTeam(MpesaTransaction $transaction, string $title, string $body): void
    {
        Notifier::send(
            Notifier::vendorTeam($transaction->vendor_id, 'vendor.mpesa.reconcile'),
            $title,
            $body,
            route('vendor.mpesa.index', ['filter' => 'attention']),
            'banknotes',
            'mpesa',
        );
    }

    /** Daraja sends times as 20261015143005 (Nairobi time). */
    public static function mpesaTime(mixed $value): CarbonImmutable
    {
        $value = (string) $value;

        return preg_match('/^\d{14}$/', $value)
            ? CarbonImmutable::createFromFormat('YmdHis', $value, 'Africa/Nairobi')->setTimezone(config('app.timezone'))
            : CarbonImmutable::now();
    }
}
