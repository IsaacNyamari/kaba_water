<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Vendor;

class ChartOfAccountsService
{
    /**
     * The standard water-utility chart (CLAUDE.md), plus 5900 for expenses
     * that fit none of the specific accounts (rent, power, office…).
     *
     * Note 1510 Accumulated Depreciation: an ASSET account with a CREDIT
     * normal side (a contra-asset) — it reduces 1500 on the balance sheet.
     *
     * @return list<array{code: string, name: string, type: AccountType, normal: string}>
     */
    public static function standardChart(): array
    {
        return [
            ['code' => '1000', 'name' => 'Cash / Bank',               'type' => AccountType::Asset,     'normal' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable',       'type' => AccountType::Asset,     'normal' => 'debit'],
            ['code' => '1200', 'name' => 'Inventory',                 'type' => AccountType::Asset,     'normal' => 'debit'],
            ['code' => '1500', 'name' => 'Meters & Equipment',        'type' => AccountType::Asset,     'normal' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation',  'type' => AccountType::Asset,     'normal' => 'credit'],
            ['code' => '2000', 'name' => 'Accounts Payable',          'type' => AccountType::Liability, 'normal' => 'credit'],
            ['code' => '2100', 'name' => 'VAT Payable',               'type' => AccountType::Liability, 'normal' => 'credit'],
            ['code' => '2200', 'name' => 'Customer Deposits',         'type' => AccountType::Liability, 'normal' => 'credit'],
            ['code' => '3000', 'name' => "Owner's Equity",            'type' => AccountType::Equity,    'normal' => 'credit'],
            ['code' => '3100', 'name' => 'Retained Earnings',         'type' => AccountType::Equity,    'normal' => 'credit'],
            ['code' => '4000', 'name' => 'Water Revenue',             'type' => AccountType::Revenue,   'normal' => 'credit'],
            ['code' => '4100', 'name' => 'Connection Fees',           'type' => AccountType::Revenue,   'normal' => 'credit'],
            ['code' => '4200', 'name' => 'Penalties & Fees',          'type' => AccountType::Revenue,   'normal' => 'credit'],
            ['code' => '5000', 'name' => 'Water Purchases',           'type' => AccountType::Expense,   'normal' => 'debit'],
            ['code' => '5100', 'name' => 'Salaries',                  'type' => AccountType::Expense,   'normal' => 'debit'],
            ['code' => '5200', 'name' => 'Maintenance',               'type' => AccountType::Expense,   'normal' => 'debit'],
            ['code' => '5300', 'name' => 'Bad Debt',                  'type' => AccountType::Expense,   'normal' => 'debit'],
            ['code' => '5400', 'name' => 'Depreciation Expense',      'type' => AccountType::Expense,   'normal' => 'debit'],
            ['code' => '5900', 'name' => 'Other Operating Expenses',  'type' => AccountType::Expense,   'normal' => 'debit'],
        ];
    }

    /**
     * Insert the standard chart for a vendor. Idempotent: existing codes
     * (including soft-deleted ones and accounts the vendor customised) are
     * left untouched; only missing codes are added. Seeded rows are
     * is_system = true so they can't be deleted or re-coded.
     */
    public function seedDefaultsFor(Vendor|int $vendor): void
    {
        $vendorId = $vendor instanceof Vendor ? $vendor->id : $vendor;

        $existing = ChartOfAccount::withoutGlobalScope('vendor')
            ->withTrashed()
            ->where('vendor_id', $vendorId)
            ->pluck('code')
            ->all();

        foreach (self::standardChart() as $row) {
            if (in_array($row['code'], $existing, true)) {
                continue;
            }

            ChartOfAccount::withoutGlobalScope('vendor')->create([
                'vendor_id' => $vendorId,
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'normal_side' => $row['normal'],
                'is_active' => true,
                'is_system' => true,
            ]);
        }
    }

    /** Seed only if the vendor has no accounts at all ("first use"). */
    public function ensureFor(Vendor|int $vendor): void
    {
        $vendorId = $vendor instanceof Vendor ? $vendor->id : $vendor;

        if (! ChartOfAccount::withoutGlobalScope('vendor')->withTrashed()->where('vendor_id', $vendorId)->exists()) {
            $this->seedDefaultsFor($vendorId);
        }
    }
}
