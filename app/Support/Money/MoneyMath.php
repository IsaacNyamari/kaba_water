<?php

namespace App\Support\Money;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Brick\Money\SplitMode;
use InvalidArgumentException;

/**
 * Thin helpers around brick/money so accounting code never touches floats.
 *
 * Values arrive as numeric strings (from DECIMAL columns / form input) or
 * ints and are rounded half-up to the currency's minor unit (2 dp for KES).
 * Floats are rejected outright — pass them through number_format() or,
 * better, never produce them.
 */
final class MoneyMath
{
    public static function currency(): string
    {
        return config('accounting.currency', 'KES');
    }

    public static function of(mixed $value, ?string $currency = null): Money
    {
        $currency ??= self::currency();

        if ($value instanceof Money) {
            return $value;
        }

        if (is_float($value)) {
            // PHP would silently coerce floats into a string|int parameter, so check explicitly.
            throw new InvalidArgumentException('Floats are not accepted for money — pass a numeric string.');
        }

        if ($value === null || $value === '') {
            return Money::zero($currency);
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Unsupported money value of type '.get_debug_type($value).'.');
        }

        if (is_string($value)) {
            $value = trim($value);
            if (! is_numeric($value)) {
                throw new InvalidArgumentException("Not a numeric amount: '{$value}'.");
            }
        }

        return Money::of($value, $currency, roundingMode: RoundingMode::HalfUp);
    }

    public static function zero(?string $currency = null): Money
    {
        return Money::zero($currency ?? self::currency());
    }

    /** "1234.50" — the canonical string form stored in DECIMAL(15,2) columns. */
    public static function str(mixed $value): string
    {
        return (string) self::of($value)->getAmount();
    }

    public static function add(mixed ...$values): string
    {
        $sum = self::zero();
        foreach ($values as $v) {
            $sum = $sum->plus(self::of($v));
        }

        return self::str($sum);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return self::str(self::of($a)->minus(self::of($b)));
    }

    /** Sum one attribute across a collection/array of models or arrays. */
    public static function sumColumn(iterable $rows, string $key): string
    {
        $sum = self::zero();
        foreach ($rows as $row) {
            $v = is_array($row) ? ($row[$key] ?? 0) : ($row->{$key} ?? 0);
            $sum = $sum->plus(self::of(is_float($v) ? number_format($v, 2, '.', '') : $v));
        }

        return self::str($sum);
    }

    public static function equals(mixed $a, mixed $b): bool
    {
        return self::of($a)->isEqualTo(self::of($b));
    }

    public static function isZero(mixed $a): bool
    {
        return self::of($a)->isZero();
    }

    public static function isNegative(mixed $a): bool
    {
        return self::of($a)->isNegative();
    }

    public static function isPositive(mixed $a): bool
    {
        return self::of($a)->isPositive();
    }

    public static function compare(mixed $a, mixed $b): int
    {
        return self::of($a)->compareTo(self::of($b));
    }

    public static function negate(mixed $a): string
    {
        return self::str(self::of($a)->negated());
    }

    public static function abs(mixed $a): string
    {
        return self::str(self::of($a)->abs());
    }

    /** a × rate (rate as a decimal string, e.g. "0.16"), rounded half-up. */
    public static function multiply(mixed $a, string|int $rate): string
    {
        return self::str(self::of($a)->multipliedBy($rate, RoundingMode::HalfUp));
    }

    /**
     * Split $total into $parts amounts that add back up exactly
     * (the leftover cents go to the first parts).
     *
     * @return list<string>
     */
    public static function split(mixed $total, int $parts): array
    {
        return array_map(fn (Money $m) => self::str($m), self::of($total)->split($parts, SplitMode::ToFirst));
    }

    /** Display helper: 1,234.50 */
    public static function format(mixed $value): string
    {
        $amount = self::of($value)->getAmount();
        $neg = $amount->isNegative();
        [$int, $dec] = array_pad(explode('.', (string) $amount->abs()), 2, '00');

        return ($neg ? '-' : '').number_format((int) $int).'.'.str_pad($dec, 2, '0');
    }
}
