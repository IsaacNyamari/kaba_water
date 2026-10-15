<?php

namespace App\Services\Sms;

use App\Models\Client;
use App\Models\Integration;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends SMS through the vendor's own Africa's Talking account. Without one
 * the message is only logged, so payment confirmations and tokens are never
 * lost — they show in the SMS log and can be read out to the customer.
 * Sending never throws: a failed SMS must not undo a payment.
 */
class SmsService
{
    public const PROVIDERS = [
        'africastalking' => "Africa's Talking",
    ];

    public function send(int $vendorId, ?string $to, string $message, ?string $purpose = null, ?Client $client = null): SmsMessage
    {
        $to = self::international($to);
        $integration = Integration::active($vendorId, Integration::SMS);

        $log = new SmsMessage([
            'vendor_id' => $vendorId,
            'client_id' => $client?->id,
            'to' => $to ?: '-',
            'message' => $message,
            'purpose' => $purpose,
            'provider' => $integration ? 'africastalking' : 'log',
            'status' => 'logged',
        ]);

        if (! $to) {
            $log->fill(['status' => 'failed', 'error' => 'No phone number.'])->save();

            return $log;
        }

        if (! $integration) {
            $log->save();

            return $log;
        }

        try {
            [$ok, $id, $error] = $this->viaAfricasTalking($integration, $to, $message);
            $log->fill(['status' => $ok ? 'sent' : 'failed', 'provider_message_id' => $id, 'error' => $error]);
            $integration->forceFill(['last_used_at' => now()])->save();
        } catch (Throwable $e) {
            $log->fill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
        }

        $log->save();

        return $log;
    }

    /**
     * @return array{0: bool, 1: ?string, 2: ?string}
     */
    protected function viaAfricasTalking(Integration $integration, string $to, string $message): array
    {
        $username = (string) $integration->setting('username');
        $sandbox = $username === 'sandbox';
        $url = $sandbox
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->withHeaders(['apiKey' => (string) $integration->setting('api_key')])
            ->post($url, array_filter([
                'username' => $username,
                'to' => $to,
                'message' => $message,
                'from' => $integration->setting('sender_id') ?: null,
            ]));

        $recipient = $response->json('SMSMessageData.Recipients.0');

        if ($response->successful() && in_array($recipient['statusCode'] ?? null, [100, 101, 102], true)) {
            return [true, $recipient['messageId'] ?? null, null];
        }

        return [false, null, mb_substr($recipient['status'] ?? $response->json('SMSMessageData.Message') ?? $response->body(), 0, 250)];
    }

    /** "0712 345 678" → "+254712345678". Returns null for anything that is not a usable number. */
    public static function international(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return match (true) {
            strlen($digits) === 12 && str_starts_with($digits, '254') => '+'.$digits,
            strlen($digits) === 10 && str_starts_with($digits, '0') => '+254'.substr($digits, 1),
            strlen($digits) === 9 && in_array($digits[0], ['7', '1'], true) => '+254'.$digits,
            default => null,
        };
    }
}
