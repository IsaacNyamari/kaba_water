<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Database-portable date SQL (MySQL/MariaDB in production, SQLite in tests). */
final class SqlDate
{
    /** SQL expression yielding 'YYYY-MM' for a column, e.g. SqlDate::yearMonth('payments.paid_at'). */
    public static function yearMonth(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
