<?php

use App\Services\GeminiOcrService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const TEST_GEMINI_KEY = 'test-gemini-key-123';

beforeEach(function () {
    config([
        'services.gemini.key' => TEST_GEMINI_KEY,
        'services.gemini.models' => ['model-a', 'model-b'],
    ]);

    $this->image = tempnam(sys_get_temp_dir(), 'meter');
    file_put_contents($this->image, 'fake-image-bytes');
});

afterEach(function () {
    @unlink($this->image);
});

test('the API key is sent in a header and never in the URL', function () {
    Http::fake([
        '*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '012 806']]]]]]),
    ]);

    $digits = app(GeminiOcrService::class)->extract($this->image, 'image/jpeg');

    expect($digits)->toBe('012806');

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', TEST_GEMINI_KEY)
        && ! str_contains($request->url(), TEST_GEMINI_KEY)
        && ! str_contains($request->url(), 'key='));
});

test('failures never expose the key in the exception or the logs', function () {
    Log::spy();

    Http::fake(function (Request $request) {
        // Connection errors quote the request URL (and any key echoed back).
        throw new ConnectionException('cURL error 28: timed out for '.$request->url().' using '.TEST_GEMINI_KEY);
    });

    try {
        app(GeminiOcrService::class)->extract($this->image, 'image/jpeg');
        $this->fail('Expected the OCR call to fail.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toContain(TEST_GEMINI_KEY)
            ->and($e->getMessage())->toBe('Automatic reading is unavailable right now.');
    }

    Log::shouldHaveReceived('warning')->twice()->withArgs(
        fn (string $message, array $context) => ! str_contains(json_encode($context), TEST_GEMINI_KEY)
    );
});

test('an error response body is logged with the key redacted', function () {
    Log::spy();

    Http::fake(['*' => Http::response('invalid key '.TEST_GEMINI_KEY, 400)]);

    expect(fn () => app(GeminiOcrService::class)->extract($this->image, 'image/jpeg'))
        ->toThrow(RuntimeException::class, 'Automatic reading is unavailable right now.');

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => str_contains($context['body'] ?? '', '[redacted]')
            && ! str_contains(json_encode($context), TEST_GEMINI_KEY)
    );
});
