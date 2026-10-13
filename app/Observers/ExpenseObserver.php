<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\Accounting\FiscalPeriodService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\ExpenseRecorded;

/**
 * Expense created → ExpenseRecorded (or WaterPurchase / SalaryPaid by category).
 * Edited money fields → reverse the old entry and post the new one.
 * Deleted → reversal.
 */
class ExpenseObserver
{
    protected const POSTED_FIELDS = ['amount', 'tax', 'expense_category_id', 'paid_at', 'payment_method'];

    public function __construct(
        protected LedgerService $ledger,
        protected FiscalPeriodService $periods,
    ) {}

    public function saving(Expense $expense): void
    {
        $vendorId = $expense->vendor_id ?? auth()->user()?->vendor_id;

        if (! $expense->exists || $expense->isDirty(self::POSTED_FIELDS)) {
            $this->periods->assertOpen($vendorId, $expense->paid_at ?? now());

            if ($expense->exists && $expense->getOriginal('paid_at')) {
                // The reversal of the old entry is dated today.
                $this->periods->assertOpen($vendorId, now());
            }
        }
    }

    public function created(Expense $expense): void
    {
        $this->ledger->postEvent(ExpenseRecorded::forExpense($expense->loadMissing('category')));
    }

    public function updated(Expense $expense): void
    {
        if (! $expense->wasChanged(self::POSTED_FIELDS)) {
            return;
        }

        $this->reverseExisting($expense, 'Expense edited');
        $this->ledger->postEvent(ExpenseRecorded::forExpense($expense->load('category')));
    }

    public function deleting(Expense $expense): void
    {
        $this->periods->assertOpen($expense->vendor_id, now());
    }

    public function deleted(Expense $expense): void
    {
        $this->reverseExisting($expense, 'Expense deleted');
    }

    protected function reverseExisting(Expense $expense, string $reason): void
    {
        $meta = ['vendor_id' => $expense->vendor_id, 'source_type' => Expense::class, 'source_id' => $expense->id, 'source_event' => ExpenseRecorded::EVENT];

        if ($entry = $this->ledger->findPosted($meta)) {
            $this->ledger->reverse($entry, $reason);
        }
    }
}
