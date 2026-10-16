<?php

namespace App\Services\Sms;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\Client;
use App\Models\Integration;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends SMS through the vendor's own bulk SMS account — each vendor picks
 * its provider (Africa's Talking or HostPinnacle) and its approved sender
 * ID. Without one the message is only logged, so payment confirmations,
 * invoices and tokens are never lost — they show in the SMS log.
 * Sending never throws: a failed SMS must not undo a payment.
 */
class SmsService
{
    public const PROVIDERS = [
        'africastalking' => "Africa's Talking",
        'hostpinnacle' => 'HostPinnacle',
    ];

    public function send(int $vendorId, ?string $to, string $message, ?string $purpose = null, ?Client $client = null): SmsMessage
    {
        $to = self::international($to);
        $integration = Integration::active($vendorId, Integration::SMS);
        $provider = self::providerOf($integration);

        $log = new SmsMessage([
            'vendor_id' => $vendorId,
            'client_id' => $client?->id,
            'to' => $to ?: '-',
            'message' => $message,
            'purpose' => $purpose,
            'provider' => $integration ? $provider : 'log',
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
            [$ok, $id, $error] = match ($provider) {
                'hostpinnacle' => $this->viaHostPinnacle($integration, $to, $message),
                default => $this->viaAfricasTalking($integration, $to, $message),
            };
            $log->fill(['status' => $ok ? 'sent' : 'failed', 'provider_message_id' => $id, 'error' => $error ? mb_substr($error, 0, 250) : null]);
            $integration->forceFill(['last_used_at' => now()])->save();
        } catch (Throwable $e) {
            $log->fill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
        }

        $log->save();

        return $log;
    }

    /** The provider a vendor chose; older setups without one use Africa's Talking. */
    public static function providerOf(?Integration $integration): string
    {
        $provider = (string) $integration?->setting('provider');

        return array_key_exists($provider, self::PROVIDERS) ? $provider : 'africastalking';
    }

    /**
     * Africa's Talking, through the official SDK. Username "sandbox" uses
     * their test environment (no real messages).
     *
     * @return array{0: bool, 1: ?string, 2: ?string}
     */
    protected function viaAfricasTalking(Integration $integration, string $to, string $message): array
    {
        $sms = (new AfricasTalking((string) $integration->setting('username'), (string) $integration->setting('api_key')))->sms();

        $result = $sms->send(array_filter([
            'to' => $to,
            'message' => $message,
            'from' => $integration->setting('sender_id') ?: null,
        ]));

        $data = json_decode(json_encode($result['data'] ?? null), true) ?: [];
        $recipient = $data['SMSMessageData']['Recipients'][0] ?? [];

        if (($result['status'] ?? null) === 'success' && in_array($recipient['statusCode'] ?? null, [100, 101, 102], true)) {
            return [true, $recipient['messageId'] ?? null, null];
        }

        return [false, null, $recipient['status'] ?? $data['SMSMessageData']['Message'] ?? (is_string($result['data'] ?? null) ? $result['data'] : 'Africa\'s Talking did not accept the message.')];
    }

    /**
     * HostPinnacle (smsportal.hostpinnacle.co.ke). Authenticates with the
     * portal user ID and password, or with an API key from the portal.
     *
     * @return array{0: bool, 1: ?string, 2: ?string}
     */
    protected function viaHostPinnacle(Integration $integration, string $to, string $message): array
    {
        $apiKey = (string) $integration->setting('hp_api_key');
        $request = Http::asForm()->acceptJson()->timeout(20);

        $params = [
            'sendMethod' => 'quick',
            'mobile' => ltrim($to, '+'),
            'msg' => $message,
            'senderid' => (string) $integration->setting('sender_id'),
            'msgType' => 'text',
            'duplicatecheck' => 'true',
            'output' => 'json',
        ];

        if (filled($integration->setting('userid'))) {
            $params['userid'] = (string) $integration->setting('userid');
            $params['password'] = (string) $integration->setting('password');
        } elseif ($apiKey !== '') {
            $request = $request->withHeaders(['apikey' => $apiKey]);
        }

        $response = $request->post('https://smsportal.hostpinnacle.co.ke/SMSApi/send', $params);
        $body = $response->json() ?? [];

        if ($response->successful() && strtolower((string) ($body['status'] ?? '')) === 'success') {
            return [true, isset($body['transactionId']) ? (string) $body['transactionId'] : null, null];
        }

        return [false, null, (string) ($body['reason'] ?? $body['message'] ?? $body['status'] ?? trim($response->body()) ?: "HTTP {$response->status()}")];
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
