<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when no Gemini API key is available for the request: the vendor has
 * not added their own and the platform key may not be used as a fallback.
 */
class GeminiKeyMissingException extends RuntimeException {}
