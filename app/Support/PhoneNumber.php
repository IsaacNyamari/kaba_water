<?php

namespace App\Support;

/**
 * Compares phone numbers however they were typed.
 *
 * Kenyan numbers are written many ways — "0712 345 678", "+254712345678",
 * "254712345678", "712345678" — and all mean the same line. normalize()
 * reduces each to one form (local, leading 0) so they can be compared.
 */
final class PhoneNumber
{
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        // +254 7xx / +254 1xx → 07xx / 01xx
        if (strlen($digits) === 12 && str_starts_with($digits, '254')) {
            return '0'.substr($digits, 3);
        }

        // 7xx xxx xxx without the leading 0
        if (strlen($digits) === 9 && in_array($digits[0], ['7', '1'], true)) {
            return '0'.$digits;
        }

        return $digits;
    }

    public static function matches(?string $a, ?string $b): bool
    {
        $a = self::normalize($a);

        return $a !== '' && $a === self::normalize($b);
    }
}
