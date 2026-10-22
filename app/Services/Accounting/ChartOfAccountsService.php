<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Vendor;

class ChartOfAccountsService
{
    /**
     * Keys of config('accounting.accounts') that a posting rule, the write-off,
     * depreciation or the opening-balance wizard actually posts to.
     */
    protected const POSTING_KEYS = [
        'cash', 'receivables', 'fixed_assets', 'accumulated_depreciation',
        'payables', 'vat_payable', 'owners_equity', 'water_revenue', 'rental_income', 'service_charges',
        'water_purchases', 'salaries', 'bad_debt', 'depreciation', 'other_expenses',
    ];

    /**
     * The standard water-utility chart (CLAUDE.md), plus 4300 / 4400 for
     * property owners' rent and service charges, and 5900 for expenses that
     * fit none of the specific accounts (rent, power, office…).
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
            ['code' => '4300', 'name' => 'Rental Income',             'type' => AccountType::Revenue,   'normal' => 'credit'],
            ['code' => '4400', 'name' => 'Service Charges',           'type' => AccountType::Revenue,   'normal' => 'credit'],
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

    /**
     * Account codes the automatic posting rules write to (config/accounting.php).
     * Deactivating one would make invoices, payments or expenses fail to post.
     *
     * @return list<string>
     */
    public static function postingCodes(): array
    {
        return collect([
            array_intersect_key(config('accounting.accounts', []), array_flip(self::POSTING_KEYS)),
            config('accounting.payment_method_accounts', []),
            config('accounting.invoice_item_accounts', []),
            config('accounting.expense_category_accounts', []),
        ])->flatten()->map(fn ($code) => (string) $code)->unique()->values()->all();
    }

    /** True when automatic postings depend on this account staying active. */
    public function isRequiredForPosting(ChartOfAccount $account): bool
    {
        return in_array((string) $account->code, self::postingCodes(), true);
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
