<?php

namespace App\Services\Seo;

use App\Services\GeminiKeyMissingException;
use App\Services\GeminiOcrService;
use App\Support\Seo\SeoRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Drafts a page's SEO title, meta description and keywords with Gemini,
 * from the page's own visible text. Uses the platform Gemini key (the same
 * one the admin configures for the platform); the admin reviews the draft
 * before saving — nothing is published automatically.
 */
class GeminiSeoWriter
{
    public function __construct(protected GeminiOcrService $gemini) {}

    /**
     * @return array{title: string, description: string, keywords: string}
     *
     * @throws GeminiKeyMissingException when no platform key is set.
     * @throws RuntimeException when every configured model fails.
     */
    public function suggest(string $pageKey): array
    {
        $page = SeoRegistry::page($pageKey) ?? throw new RuntimeException('Unknown page.');
        $apiKey = $this->gemini->keyFor(null);
        $models = config('services.gemini.models', []);

        if (empty($models)) {
            throw new RuntimeException('Gemini models are not configured.');
        }

        $prompt = $this->prompt($page['label'], route($pageKey), $this->pageText($page));
        $lastError = null;

        foreach ($models as $model) {
            try {
                $response = Http::timeout(30)
                    ->withOptions(GeminiOcrService::NETWORK_OPTIONS)
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $apiKey])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent', [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'maxOutputTokens' => 400,
                            'responseMimeType' => 'application/json',
                            'responseSchema' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'title' => ['type' => 'STRING'],
                                    'description' => ['type' => 'STRING'],
                                    'keywords' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                                ],
                                'required' => ['title', 'description', 'keywords'],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $data = json_decode((string) $response->json('candidates.0.content.parts.0.text', ''), true);

                    if (is_array($data) && filled($data['title'] ?? null) && filled($data['description'] ?? null)) {
                        return $this->clean($data);
                    }

                    $lastError = "{$model} → unreadable answer";

                    continue;
                }

                $lastError = "{$model} → HTTP {$response->status()}";
                Log::warning('Gemini SEO model failed', ['model' => $model, 'status' => $response->status(), 'body' => str_replace($apiKey, '[redacted]', Str::limit($response->body(), 500))]);
            } catch (\Throwable $e) {
                $lastError = "{$model} → ".class_basename($e);
                Log::warning('Gemini SEO request error', ['model' => $model, 'error' => str_replace($apiKey, '[redacted]', $e->getMessage())]);
            }
        }

        throw new RuntimeException('Gemini could not write a suggestion right now ('.$lastError.'). Try again in a minute.');
    }

    /** @return array{title: string, description: string, keywords: string} */
    protected function clean(array $data): array
    {
        $keywords = collect(is_array($data['keywords'] ?? null) ? $data['keywords'] : explode(',', (string) ($data['keywords'] ?? '')))
            ->map(fn ($k) => trim(Str::lower((string) $k)))
            ->filter()
            ->unique()
            ->take(10)
            ->implode(', ');

        return [
            'title' => Str::limit(trim(strip_tags((string) $data['title'])), 60, ''),
            'description' => Str::limit(trim(strip_tags((string) $data['description'])), 160, ''),
            'keywords' => Str::limit($keywords, 255, ''),
        ];
    }

    /** The page's visible main text (or its default description for pages without a marketing view). */
    protected function pageText(array $page): string
    {
        $text = '';

        if ($page['view']) {
            $html = rescue(fn () => view($page['view'])->render(), '', false);

            if (preg_match('#<main[^>]*>(.*)</main>#si', $html, $match)) {
                $html = $match[1];
            }

            $html = preg_replace('#<(script|style|svg|noscript)[^>]*>.*?</\1>#si', ' ', $html);
            $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5);
            $text = trim(preg_replace('/\s+/u', ' ', $text));
        }

        return Str::limit($text !== '' ? $text : $page['description'], 6000, '');
    }

    protected function prompt(string $label, string $url, string $text): string
    {
        $brand = config('app.name');

        return <<<PROMPT
You are an SEO copywriter for {$brand} (also "HumSols"), a Kenyan software company in Nairobi. Its product on this website is "HS Water Billing", water billing software for water vendors, borehole operators and estate managers in Kenya. Prices are in Kenya shillings (KES).

Write SEO metadata for the page "{$label}" ({$url}) using ONLY facts found in the page text below. Do not invent prices, numbers, clients or claims.

Rules:
- title: 45–60 characters, the page's main topic first, natural wording, do not add the brand name (it is appended automatically).
- description: 140–160 characters, plain and specific, includes a reason to click, written for people searching in Kenya.
- keywords: 5–10 lowercase search phrases people in Kenya would actually type, most important first.
- British/Kenyan English spelling. No emojis, no quotation marks, no ALL CAPS.

Page text:
"""
{$text}
"""
PROMPT;
    }
}
