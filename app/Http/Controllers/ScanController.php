<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScanController extends Controller
{
    public function index()
    {
        return view('meter.reading.scan');
    }

    public function extractText(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        try {
            // 1. Prepare image payload strings
            $imagePath = $request->file('image')->path();
            $mimeType = $request->file('image')->getMimeType();
            $base64Image = base64_encode(file_get_contents($imagePath));

            // 2. Parse out models list from your .env configuration
            // Best practice: use config() instead of env() directly in controllers
            $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
            $modelsEnv = env('GEMINI_MODELS', 'gemini-flash-lite-latest,gemini-flash-latest');
            $models = array_filter(array_map('trim', explode(',', $modelsEnv)));

            $lastError = 'No models available';

            // 3. Fallover Rotation Loop: Tries models in sequential order
            foreach ($models as $model) {
                // FIX: Correct fully qualified Google Generative AI REST endpoint path
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apiKey;

                $response = Http::timeout(15)->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'Analyze this utility meter image. Find the main odometer horizontal reading strip. Extract the digits exactly as they appear sequentially from left to right. Return ONLY the final digits as a single continuous string of numbers. Do not include spaces, words, symbols, units like m³, or explanations.'
                                ],
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => $base64Image
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);

                // If successful, break rotation immediately and return payload data
                if ($response->successful()) {
                    $result = $response->json();
                    $extractedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $cleanReading = trim(preg_replace('/\s+/', '', $extractedText));

                    return response()->json([
                        'success' => true,
                        'model_used' => $model,
                        'text' => $cleanReading
                    ]);
                }

                // Log error status if this specific model fallback step fails
                $lastError = "Model {$model} failed: (Status {$response->status()}) " . $response->body();
            }

            // All models in config stream failed
            return response()->json([
                'success' => false,
                'message' => 'All configured Gemini models failed or reached tier rate limits.',
                'debug_context' => $lastError
            ], 429);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Processing pipeline stalled: ' . $e->getMessage()
            ], 500);
        }
    }
}
