<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Integration;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Meter;
use App\Models\MeterTelemetry;
use App\Models\MpesaTransaction;
use App\Models\Payment;
use App\Models\PrepaidToken;
use App\Models\Reading;
use App\Models\SmsMessage;
use App\Models\Tariff;
use App\Services\Metering\SmartMeterReadings;
use App\Services\Mpesa\MpesaReconciler;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->withoutDefer();
    $this->vendor = accountingVendor();
    $this->mpesa = Integration::for($this->vendor->id, Integration::MPESA);
    $this->mpesa->update(['is_enabled' => true, 'config' => ['shortcode' => '600000', 'environment' => 'sandbox']]);
    $this->client = Client::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'account_no' => 'C00001', 'name' => 'Jane Customer',
        'phone' => '0712345678', 'house_number' => 'B4',
    ]);
});

function mpesaInvoice($test, string $total, string $issuedAt = 'now', string $status = 'sent'): Invoice
{
    return Invoice::withoutGlobalScope('vendor')->create([
        'vendor_id' => $test->vendor->id, 'client_id' => $test->client->id,
        'number' => 'INV-'.random_int(10000, 99999), 'issued_at' => $issuedAt, 'due_at' => now()->addDays(14),
        'subtotal' => $total, 'tax' => '0.00', 'total' => $total, 'status' => $status,
    ]);
}

function c2b($test, string $receipt, string $amount, string $account, string $phone = '254799000111'): TestResponse
{
    return $test->postJson(route('webhooks.mpesa.confirmation', $test->mpesa->webhook_token), [
        'TransactionType' => 'Pay Bill', 'TransID' => $receipt, 'TransTime' => '20261015101500',
        'TransAmount' => $amount, 'BusinessShortCode' => '600000', 'BillRefNumber' => $account,
        'MSISDN' => $phone, 'FirstName' => 'JANE',
    ]);
}

function ledgerFor(string $type, int $id, string $event)
{
    return JournalEntry::withoutGlobalScope('vendor')->with('lines.account')
        ->where('source_type', $type)->where('source_id', $id)->where('source_event', $event)->first();
}

test('a paybill payment with the account number settles the oldest invoice, logs the receipt and texts the customer', function () {
    $old = mpesaInvoice($this, '500.00', now()->subMonth()->toDateTimeString());
    $new = mpesaInvoice($this, '800.00');

    c2b($this, 'QWE12RT34Y', '700.00', 'c00001')->assertOk()->assertJson(['ResultCode' => 0]);

    $t = MpesaTransaction::withoutGlobalScope('vendor')->where('receipt', 'QWE12RT34Y')->firstOrFail();
    expect($t->status)->toBe('matched')
        ->and($t->client_id)->toBe($this->client->id)
        ->and((string) $t->allocated_amount)->toBe('700.00')
        ->and($old->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and((string) $new->fresh()->amount_paid)->toBe('200.00');

    $payments = Payment::withoutGlobalScope('vendor')->where('mpesa_transaction_id', $t->id)->get();
    expect($payments)->toHaveCount(2)
        ->and($payments->pluck('reference')->unique()->all())->toBe(['QWE12RT34Y'])
        ->and($payments->every(fn ($p) => ! $p->from_customer_deposit))->toBeTrue();

    expect(SmsMessage::withoutGlobalScope('vendor')->where('purpose', 'payment_confirmation')->value('message'))
        ->toContain('QWE12RT34Y')->toContain('600.00');
});

test('the same M-Pesa receipt is only recorded once', function () {
    mpesaInvoice($this, '1000.00');

    c2b($this, 'DUP0000001', '300.00', 'C00001');
    c2b($this, 'DUP0000001', '300.00', 'C00001');

    expect(MpesaTransaction::withoutGlobalScope('vendor')->count())->toBe(1)
        ->and(Payment::withoutGlobalScope('vendor')->count())->toBe(1);
});

test('an unknown account goes to suspense as a customer deposit and can be applied later', function () {
    $invoice = mpesaInvoice($this, '400.00');

    c2b($this, 'SUSP000001', '400.00', 'NOPE-99', '254700000999');

    $t = MpesaTransaction::withoutGlobalScope('vendor')->where('receipt', 'SUSP000001')->firstOrFail();
    expect($t->status)->toBe('suspense')->and($t->client_id)->toBeNull();

    $held = ledgerFor(MpesaTransaction::class, $t->id, 'mpesa.deposit');
    expect($held->lines->firstWhere('account.code', '1000')->debit)->toBe('400.00')
        ->and($held->lines->firstWhere('account.code', '2200')->credit)->toBe('400.00');

    $accountant = accountingUser($this->vendor, 'accountant');
    $this->actingAs($accountant);
    app(MpesaReconciler::class)->assign($t, $this->client, null, $accountant->id);

    $payment = Payment::withoutGlobalScope('vendor')->where('mpesa_transaction_id', $t->id)->firstOrFail();
    $posted = ledgerFor(Payment::class, $payment->id, 'payment.received');

    expect($t->fresh()->status)->toBe('matched')
        ->and($t->fresh()->reconciled_by)->toBe($accountant->id)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($payment->from_customer_deposit)->toBeTrue()
        ->and($posted->lines->firstWhere('account.code', '2200')->debit)->toBe('400.00')
        ->and($posted->lines->firstWhere('account.code', '1100')->credit)->toBe('400.00');
});

test('the house number and a unique phone number also identify the customer', function () {
    c2b($this, 'HOUSE00001', '50.00', 'b 4');
    c2b($this, 'PHONE00001', '50.00', 'whatever', '254712345678');

    expect(MpesaTransaction::withoutGlobalScope('vendor')->whereIn('receipt', ['HOUSE00001', 'PHONE00001'])->pluck('client_id')->unique()->all())
        ->toBe([$this->client->id]);
});

test('an overpayment is held as credit and settles the next invoice when it is sent', function () {
    mpesaInvoice($this, '300.00');
    c2b($this, 'OVER000001', '500.00', 'C00001');

    $t = MpesaTransaction::withoutGlobalScope('vendor')->where('receipt', 'OVER000001')->firstOrFail();
    expect($t->status)->toBe('credit')->and($t->unallocated())->toBe('200.00');

    $this->actingAs(accountingUser($this->vendor));
    $next = mpesaInvoice($this, '150.00', 'now', 'draft');
    $next->update(['status' => InvoiceStatus::Sent]);

    expect($next->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($t->fresh()->unallocated())->toBe('50.00')
        ->and($t->fresh()->status)->toBe('credit');
});

test('paying a prepaid meter number deducts the arrears share and texts an STS token', function () {
    Tariff::withoutGlobalScope('vendor')->create(['vendor_id' => $this->vendor->id, 'name' => 'Flat', 'type' => 'flat', 'rate' => '50', 'is_active' => true]);
    Integration::for($this->vendor->id, Integration::TOKEN_VENDING)->update([
        'is_enabled' => true, 'config' => ['driver' => 'simulator', 'arrears_percent' => '25'],
    ]);
    Meter::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'client_id' => $this->client->id, 'serial' => 'PP-1001', 'status' => 'active', 'billing_mode' => 'prepaid',
    ]);
    $arrears = mpesaInvoice($this, '1160.00');

    c2b($this, 'TOKEN00001', '1000.00', 'PP-1001');

    $token = PrepaidToken::withoutGlobalScope('vendor')->firstOrFail();
    expect($token->status)->toBe('issued')
        ->and(strlen($token->token))->toBe(20)
        ->and((string) $token->arrears_deducted)->toBe('250.00')
        ->and((string) $token->token_amount)->toBe('750.00')
        ->and((string) $token->units)->toBe('15.000')
        ->and((string) $arrears->fresh()->amount_paid)->toBe('250.00')
        ->and(MpesaTransaction::withoutGlobalScope('vendor')->value('status'))->toBe('matched');

    $sale = ledgerFor(PrepaidToken::class, $token->id, 'prepaid.token_sold');
    expect($sale->lines->firstWhere('account.code', '4000')->credit)->toBe('750.00');

    expect(SmsMessage::withoutGlobalScope('vendor')->where('purpose', 'prepaid_token')->value('message'))
        ->toContain($token->formattedToken());
});

test('an STK callback completes the pending request, and a cancelled one is marked failed', function () {
    $invoice = mpesaInvoice($this, '250.00');
    $pending = fn (string $id) => MpesaTransaction::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'channel' => 'stk', 'purpose' => 'bill', 'checkout_request_id' => $id,
        'client_id' => $this->client->id, 'invoice_id' => $invoice->id, 'amount' => '250', 'phone' => '254712345678', 'status' => 'pending',
    ]);
    $ok = $pending('ws_CO_1');
    $cancelled = $pending('ws_CO_2');
    $url = route('webhooks.mpesa.stk', $this->mpesa->webhook_token);

    $this->postJson($url, ['Body' => ['stkCallback' => [
        'CheckoutRequestID' => 'ws_CO_1', 'ResultCode' => 0, 'ResultDesc' => 'Processed',
        'CallbackMetadata' => ['Item' => [
            ['Name' => 'Amount', 'Value' => 250], ['Name' => 'MpesaReceiptNumber', 'Value' => 'STKOK00001'],
            ['Name' => 'TransactionDate', 'Value' => 20261015101500], ['Name' => 'PhoneNumber', 'Value' => 254712345678],
        ]],
    ]]])->assertOk();
    $this->postJson($url, ['Body' => ['stkCallback' => ['CheckoutRequestID' => 'ws_CO_2', 'ResultCode' => 1032, 'ResultDesc' => 'Request cancelled by user']]])->assertOk();

    expect($ok->fresh()->status)->toBe('matched')
        ->and($ok->fresh()->receipt)->toBe('STKOK00001')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($cancelled->fresh()->status)->toBe('failed');
});

test('callbacks with an unknown or disabled token are refused', function () {
    $this->postJson(route('webhooks.mpesa.confirmation', 'not-a-real-token'), [])->assertNotFound();

    $this->mpesa->update(['is_enabled' => false]);
    c2b($this, 'OFF0000001', '10.00', 'C00001')->assertNotFound();
});

test('smart meter uplinks from ChirpStack are stored, alert the team and become the monthly reading', function () {
    $smart = Integration::for($this->vendor->id, Integration::SMART_METERS);
    $smart->update(['is_enabled' => true, 'config' => ['value_field' => 'totalLitres', 'value_divisor' => '1000', 'auto_approve' => true]]);
    $meter = Meter::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'client_id' => $this->client->id, 'serial' => 'SM-1', 'status' => 'active', 'device_id' => '70b3d5e75e00aa01',
    ]);

    $this->postJson(route('webhooks.meters', $smart->webhook_token).'?event=up', [
        'deviceInfo' => ['devEui' => '70B3D5E75E00AA01'],
        'time' => now()->startOfMonth()->addDays(2)->toIso8601String(),
        'fCnt' => 12,
        'object' => ['totalLitres' => 123456, 'battery' => 81, 'leak' => true],
    ])->assertOk()->assertJson(['accepted' => 1]);

    $row = MeterTelemetry::withoutGlobalScope('vendor')->firstOrFail();
    expect((string) $row->value)->toBe('123.456')
        ->and($meter->fresh()->battery_percent)->toBe(81)
        ->and($meter->fresh()->alerts)->toBe(['leak']);

    expect(app(SmartMeterReadings::class)->createFor($this->vendor->id, now()->startOfMonth()))->toBe(1);

    $reading = Reading::withoutGlobalScope('vendor')->where('meter_id', $meter->id)->firstOrFail();
    expect($reading->reading)->toBe(123)->and($reading->source)->toBe('smart_meter')->and($reading->isApproved())->toBeTrue();
});
