<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiOcrService
{
    /**
     * Send the image to Gemini and return the extracted digits.
     *
     * The API key travels only in the x-goog-api-key header, never in the
     * URL, so it cannot surface in exception messages, logs or the UI.
     * Failures are logged (with the key scrubbed) and reported to callers
     * as a generic message.
     *
     * With a vendor, that vendor's own key is used (each vendor carries its
     * own quota); the platform key is only used for them when the admin has
     * enabled the fallback. Without a vendor (platform use) the platform key
     * is used.
     *
     * @throws GeminiKeyMissingException when no key is available.
     * @throws RuntimeException when all configured models fail.
     */
    public function extract(string $imagePath, string $mimeType, ?Vendor $vendor = null): string
    {
        $apiKey = $this->keyFor($vendor);
        $models = config('services.gemini.models', []);

        if (empty($models)) {
            throw new RuntimeException('Gemini models are not configured.');
        }

        if (! is_readable($imagePath)) {
            throw new RuntimeException('Image file is not readable.');
        }

        $base64 = base64_encode(file_get_contents($imagePath));
        $lastError = null;

        foreach ($models as $model) {
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';

            try {
                $response = Http::timeout(25)
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $apiKey])
                    ->post($endpoint, [
                        'contents' => [[
                            'parts' => [
                                ['text' => $this->prompt()],
                                ['inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64,
                                ]],
                            ],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.1,
                            'maxOutputTokens' => 64,
                        ],
                    ]);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text', '');

                    Log::info('Gemini OCR succeeded', [
                        'model' => $model,
                        'preview' => substr($text, 0, 40),
                    ]);

                    return $this->normalize($text);
                }

                $lastError = "{$model} → HTTP {$response->status()}";
                Log::warning('Gemini OCR model failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'body' => $this->redact($response->body(), $apiKey),
                ]);
            } catch (\Throwable $e) {
                $lastError = "{$model} → ".class_basename($e);
                Log::warning('Gemini OCR exception', [
                    'model' => $model,
                    'error' => $this->redact($e->getMessage(), $apiKey),
                ]);
            }
        }

        Log::error('Gemini OCR failed on every model', ['last_error' => $lastError]);

        throw new RuntimeException('Automatic reading is unavailable right now.');
    }

    /** Remove the API key from any text before it is logged. */
    protected function redact(string $text, string $apiKey): string
    {
        return str_replace($apiKey, '[redacted]', $text);
    }

    /**
     * The API key to use: the vendor's own, else (if allowed) the platform key.
     *
     * @throws GeminiKeyMissingException
     */
    public function keyFor(?Vendor $vendor): string
    {
        if ($vendor && filled($vendor->gemini_api_key)) {
            return $vendor->gemini_api_key;
        }

        $platformKey = (string) config('services.gemini.key');
        $mayUsePlatform = ! $vendor || config('services.gemini.platform_fallback');

        if ($mayUsePlatform && $platformKey !== '') {
            return $platformKey;
        }

        throw new GeminiKeyMissingException($vendor
            ? 'Add your Gemini API key in Settings → Integrations to read meter photos automatically.'
            : 'No Gemini API key is configured.');
    }

    /**
     * Check that a key is accepted by Gemini (lists models; no image sent,
     * so it costs nothing). Returns null on success, or a short reason.
     */
    public function verifyKey(string $apiKey): ?string
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->get('https://generativelanguage.googleapis.com/v1beta/models', ['pageSize' => 1]);
        } catch (\Throwable $e) {
            Log::warning('Gemini key check failed', ['error' => $this->redact($e->getMessage(), $apiKey)]);

            return "Could not reach Gemini. Check the server's internet connection and try again.";
        }

        return match (true) {
            $response->successful() => null,
            in_array($response->status(), [400, 401, 403], true) => 'Gemini rejected this key. Check it was copied in full and that the Generative Language API is enabled.',
            $response->status() === 429 => 'The key works but has hit its rate limit right now.',
            default => 'Gemini returned an unexpected response (HTTP '.$response->status().').',
        };
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
            You are reading a utility water or gas meter image.

            Locate the main horizontal odometer strip (the row of black digits).
            Extract ONLY the BLACK digits — these represent whole cubic meters (m³).

            IGNORE:
            - Red digits (they represent decimal fractions)
            - The small circular dials at the bottom
            - Any text, logos, barcodes, or labels

            Return ONLY the digits as a single continuous string, preserving any leading zeros.
            No spaces. No punctuation. No explanations.

            Example output: 012806
            PROMPT;
    }

    protected function normalize(string $text): string
    {
        // Keep only digits; drop whitespace, punctuation, stray letters
        return preg_replace('/\D+/', '', $text) ?? '';
    }
}
