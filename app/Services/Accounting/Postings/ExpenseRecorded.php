<?php

namespace App\Services\Accounting\Postings;

use App\Models\Expense;
use App\Support\Money\MoneyMath;

/**
 * An operating expense is paid.
 *   Dr 5xxx expense account (by category, config/accounting.php)   amount + tax
 *   Cr 1000 Cash / Bank — or 2000 Accounts Payable when on credit
 *
 * Tax on expenses is treated as part of the cost (no input-VAT recovery).
 */
class ExpenseRecorded extends Posting
{
    public const EVENT = 'expense.recorded';

    public function __construct(
        public int $vendorId,
        public string $amount,
        public \DateTimeInterface|string $date,
        public string $memo,
        public ?string $expenseCode = null,
        public bool $onCredit = false,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $reference = null,
    ) {}

    /** Pick the most specific rule for an Expense row. */
    public static function forExpense(Expense $e): Posting
    {
        $slug = $e->category?->slug;

        return match ($slug) {
            'water-purchase' => WaterPurchase::fromExpense($e),
            'salaries' => SalaryPaid::fromExpense($e),
            default => static::fromExpense($e),
        };
    }

    public static function fromExpense(Expense $e): static
    {
        $slug = $e->category?->slug;
        $code = $slug ? config("accounting.expense_category_accounts.{$slug}") : null;

        return new static(
            vendorId: $e->vendor_id,
            amount: MoneyMath::add((string) $e->amount, (string) $e->tax),
            date: $e->paid_at ?? now(),
            memo: $e->description,
            expenseCode: $code ?: self::code('other_expenses'),
            sourceType: Expense::class,
            sourceId: $e->id,
            reference: $e->reference,
        );
    }

    protected function debitCode(): string
    {
        return $this->expenseCode ?? self::code('other_expenses');
    }

    public function lines(): array
    {
        return [
            self::dr($this->debitCode(), $this->amount, ['description' => $this->memo]),
            self::cr($this->onCredit ? self::code('payables') : self::code('cash'), $this->amount, ['description' => $this->memo]),
        ];
    }

    public function meta(): array
    {
        return [
            'vendor_id' => $this->vendorId,
            'date' => $this->date,
            'reference' => $this->reference,
            'memo' => $this->memo,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'source_event' => self::EVENT,
        ];
    }
}
