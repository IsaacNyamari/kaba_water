<?php

namespace App\Support;

/**
 * Meter dials show leading zeros ("00120"), but readings are stored and
 * compared as whole numbers. Laravel's integer rule rejects leading zeros,
 * so readings are normalised before validation and maths.
 */
final class MeterValue
{
    /**
     * "00120" → "120", "000" → "0", " 0042 " → "42". Anything that is not
     * all digits is returned trimmed but otherwise untouched, so validation
     * still reports it.
     */
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            return $value;
        }

        $stripped = ltrim($value, '0');

        return $stripped === '' ? '0' : $stripped;
    }
}
