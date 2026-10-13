<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** The side that increases this kind of account. */
    public function normalSide(): NormalSide
    {
        return match ($this) {
            self::Asset, self::Expense => NormalSide::Debit,
            default => NormalSide::Credit,
        };
    }

    /** Flux badge colour (CLAUDE.md UI rules). */
    public function color(): string
    {
        return match ($this) {
            self::Asset => 'emerald',
            self::Liability => 'amber',
            self::Equity => 'violet',
            self::Revenue => 'blue',
            self::Expense => 'rose',
        };
    }

    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }

    public function isIncomeStatement(): bool
    {
        return ! $this->isBalanceSheet();
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
