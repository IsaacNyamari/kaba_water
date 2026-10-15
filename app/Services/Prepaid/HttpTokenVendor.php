<?php

namespace App\Services\Prepaid;

use App\Models\Integration;
use App\Models\Meter;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Any vending system with a JSON web API (the meter maker's cloud vending
 * service, or a small adapter in front of it).
 *
 * Sends POST {url} with
 *   {"meter_number": "...", "amount": "100.00", "units": "1.250", "reference": "...", "vend_by": "amount|units"}
 * and an optional auth header, and reads the token (and units) from the
 * JSON response at the configured paths (dot notation, e.g. "data.token").
 */
class HttpTokenVendor implements TokenVendor
{
    public function __construct(protected Integration $integration) {}

    public function vend(Meter $meter, string $amount, string $units, string $reference): TokenResult
    {
        $url = (string) $this->integration->setting('http.url');

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'https://')) {
            throw new TokenVendingException('The vending API address must be an https:// URL.');
        }

        $request = Http::timeout(30)->acceptJson();

        if ($header = $this->integration->setting('http.auth_header')) {
            $request = $request->withHeaders([$header => (string) $this->integration->setting('http.auth_value')]);
        }

        try {
            $response = $request->post($url, [
                'meter_number' => $meter->serial,
                'amount' => $amount,
                'units' => $units,
                'reference' => $reference,
                'vend_by' => $this->integration->setting('http.vend_by', 'amount'),
            ]);
        } catch (Throwable $e) {
            throw new TokenVendingException('Could not reach the vending system: '.$e->getMessage(), previous: $e);
        }

        $token = preg_replace('/\D+/', '', (string) data_get($response->json(), $this->integration->setting('http.token_path') ?: 'token'));

        if (! $response->successful() || strlen($token) !== 20) {
            $message = data_get($response->json(), 'message') ?? data_get($response->json(), 'error') ?? "HTTP {$response->status()}";

            throw new TokenVendingException('The vending system did not return a 20-digit token ('.mb_substr((string) $message, 0, 150).').');
        }

        $returnedUnits = data_get($response->json(), $this->integration->setting('http.units_path') ?: 'units');

        return new TokenResult(
            $token,
            is_numeric($returnedUnits) ? (string) $returnedUnits : null,
            (string) (data_get($response->json(), 'reference') ?? $reference),
        );
    }
}
