<?php

use App\Models\Integration;
use App\Services\Sms\SmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('HostPinnacle gets the user ID, password and API key together', function () {
    $vendor = accountingVendor();
    Integration::for($vendor->id, Integration::SMS)->update([
        'is_enabled' => true,
        'config' => ['provider' => 'hostpinnacle', 'sender_id' => 'TEST-ID', 'userid' => 'demo', 'password' => 'secret', 'hp_api_key' => 'key-123'],
    ]);
    Http::fake(['smsportal.hostpinnacle.co.ke/*' => Http::response(['status' => 'success', 'transactionId' => 99])]);

    $log = app(SmsService::class)->send($vendor->id, '0712345678', 'Hello');

    expect($log->status)->toBe('sent');
    Http::assertSent(fn (Request $request) => $request->hasHeader('apikey', 'key-123')
        && $request['userid'] === 'demo'
        && $request['password'] === 'secret'
        && $request['mobile'] === '254712345678');
});
