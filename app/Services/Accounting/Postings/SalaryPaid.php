<?php

namespace App\Services\Accounting\Postings;

/**
 * Salaries / wages paid.
 *   Dr 5100 Salaries
 *   Cr 1000 Cash / Bank
 */
class SalaryPaid extends ExpenseRecorded
{
    protected function debitCode(): string
    {
        return self::code('salaries');
    }
}
