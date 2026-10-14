<?php

namespace App\Services;

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
     * @throws RuntimeException when all configured models fail.
     */
    public function extract(string $imagePath, string $mimeType): string
    {
        $apiKey = config('services.gemini.key');
        $models = config('services.gemini.models', []);

        if (! $apiKey || empty($models)) {
            throw new RuntimeException('Gemini API key or models are not configured.');
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
