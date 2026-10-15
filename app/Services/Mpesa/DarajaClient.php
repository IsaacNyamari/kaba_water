<?php

namespace App\Services\Mpesa;

use App\Models\Integration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Safaricom Daraja API for one vendor's Paybill / Till.
 *
 * Config keys (Integration::MPESA): environment (sandbox|production),
 * shortcode, shortcode_type (paybill|till), store_number (Buy Goods head
 * office number, used for STK on a till), consumer_key, consumer_secret,
 * passkey.
 */
class DarajaClient
{
    public function __construct(protected Integration $integration) {}

    public function baseUrl(): string
    {
        return $this->integration->setting('environment') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /** OAuth access token, cached for just under its one-hour life. */
    public function accessToken(): string
    {
        $key = 'daraja-token:'.$this->integration->id.':'.md5((string) $this->integration->setting('consumer_key'));

        return Cache::remember($key, now()->addMinutes(55), function () {
            $response = Http::timeout(20)
                ->withBasicAuth((string) $this->integration->setting('consumer_key'), (string) $this->integration->setting('consumer_secret'))
                ->get($this->baseUrl().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

            $token = $response->json('access_token');

            if (! $response->successful() || ! $token) {
                throw new RuntimeException('M-Pesa rejected the consumer key / secret ('.($response->json('errorMessage') ?? $response->status()).').');
            }

            return $token;
        });
    }

    /**
     * Send an STK push: the customer's phone shows a PIN prompt for $amount.
     * Returns Safaricom's MerchantRequestID / CheckoutRequestID.
     *
     * @return array{MerchantRequestID: string, CheckoutRequestID: string, CustomerMessage?: string}
     */
    public function stkPush(string $phone254, int $amount, string $accountReference, string $description, string $callbackUrl): array
    {
        $shortcode = (string) $this->integration->setting('shortcode');
        $isTill = $this->integration->setting('shortcode_type') === 'till';
        // On a till the password uses the store (head office) number; the money goes to the till.
        $businessCode = $isTill ? (string) ($this->integration->setting('store_number') ?: $shortcode) : $shortcode;
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        $response = $this->http()->post($this->baseUrl().'/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $businessCode,
            'Password' => base64_encode($businessCode.$this->integration->setting('passkey').$timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => $isTill ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone254,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone254,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => mb_substr($accountReference, 0, 12),
            'TransactionDesc' => mb_substr($description, 0, 13),
        ]);

        if (! $response->successful() || (string) $response->json('ResponseCode') !== '0') {
            throw new RuntimeException($response->json('errorMessage') ?? $response->json('ResponseDescription') ?? 'M-Pesa did not accept the payment request.');
        }

        return $response->json();
    }

    /**
     * Tell Safaricom where to send Paybill / Till payments (C2B v2).
     * Production shortcodes can only register once unless Safaricom resets it.
     */
    public function registerC2bUrls(string $confirmationUrl, string $validationUrl): array
    {
        $response = $this->http()->post($this->baseUrl().'/mpesa/c2b/v2/registerurl', [
            'ShortCode' => (string) $this->integration->setting('shortcode'),
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL' => $validationUrl,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('errorMessage') ?? $response->json('ResponseDescription') ?? 'M-Pesa refused the callback URLs.');
        }

        return $response->json();
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(30)->acceptJson()->withToken($this->accessToken());
    }

    /** "0712345678" → "254712345678" (Daraja wants no plus sign). */
    public static function msisdn(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return match (true) {
            strlen($digits) === 12 && str_starts_with($digits, '254') => $digits,
            strlen($digits) === 10 && str_starts_with($digits, '0') => '254'.substr($digits, 1),
            strlen($digits) === 9 && in_array($digits[0], ['7', '1'], true) => '254'.$digits,
            default => null,
        };
    }
}
