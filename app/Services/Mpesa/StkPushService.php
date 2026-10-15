<?php

namespace App\Services\Mpesa;

use App\Models\Integration;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\MpesaTransaction;
use App\Support\Money\MoneyMath;
use Brick\Math\RoundingMode;
use RuntimeException;

/**
 * Starts "1-tap" payments: sends an M-Pesa PIN prompt to the customer's
 * phone for an invoice balance or a prepaid token, and records the pending
 * transaction the callback will complete.
 */
class StkPushService
{
    public function forInvoice(Invoice $invoice, string $phone): MpesaTransaction
    {
        $balance = MoneyMath::sub((string) $invoice->total, (string) $invoice->amount_paid);

        if (! $invoice->status->isPayable() || ! MoneyMath::isPositive($balance)) {
            throw new RuntimeException('This invoice has nothing left to pay.');
        }

        return $this->request($invoice->vendor_id, $phone, $balance, $invoice->number, 'Water bill', [
            'purpose' => 'bill',
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function forMeterToken(Meter $meter, string $phone, string $amount): MpesaTransaction
    {
        if (! $meter->isPrepaid()) {
            throw new RuntimeException('Tokens can only be bought for prepaid meters.');
        }

        if (MoneyMath::compare($amount, '10') < 0) {
            throw new RuntimeException('The minimum token purchase is KES 10.');
        }

        return $this->request($meter->vendor_id, $phone, $amount, $meter->serial, 'Water token', [
            'purpose' => 'token',
            'client_id' => $meter->client_id,
            'meter_id' => $meter->id,
        ]);
    }

    /**
     * Send a PIN prompt and record the pending transaction the callback completes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function request(int $vendorId, string $phone, string $amount, string $reference, string $description, array $attributes): MpesaTransaction
    {
        $integration = Integration::active($vendorId, Integration::MPESA)
            ?? throw new RuntimeException('M-Pesa payments are not switched on for this water provider yet.');

        $msisdn = DarajaClient::msisdn($phone)
            ?? throw new RuntimeException('Enter a Safaricom number like 0712 345 678.');

        // M-Pesa only takes whole shillings; round up so the balance is cleared.
        $whole = (int) (string) MoneyMath::of($amount)->getAmount()->toScale(0, RoundingMode::Up);

        $response = (new DarajaClient($integration))->stkPush(
            $msisdn,
            $whole,
            $reference,
            $description,
            route('webhooks.mpesa.stk', $integration->webhook_token),
        );

        return MpesaTransaction::withoutGlobalScope('vendor')->create($attributes + [
            'vendor_id' => $vendorId,
            'channel' => 'stk',
            'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $response['MerchantRequestID'] ?? null,
            'phone' => $msisdn,
            'amount' => (string) $whole,
            'account_reference' => $reference,
            'short_code' => (string) $integration->setting('shortcode'),
            'status' => 'pending',
            'transacted_at' => now(),
        ]);
    }
}
