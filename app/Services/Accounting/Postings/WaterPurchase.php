<?php

namespace App\Services\Accounting\Postings;

/**
 * Bulk water bought for resale.
 *   Dr 5000 Water Purchases
 *   Cr 2000 Accounts Payable (on credit) or 1000 Cash / Bank
 */
class WaterPurchase extends ExpenseRecorded
{
    protected function debitCode(): string
    {
        return self::code('water_purchases');
    }
}
