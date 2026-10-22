<?php

/*
|--------------------------------------------------------------------------
| Accounting — posting rule account mapping
|--------------------------------------------------------------------------
| Codes refer to each vendor's chart of accounts (seeded by
| App\Services\Accounting\ChartOfAccountsService). Change a mapping here to
| route postings to a different account without touching code.
*/

return [

    /* Default currency for vendors without one. */
    'currency' => 'KES',

    'accounts' => [
        'cash' => '1000',
        'receivables' => '1100',
        'inventory' => '1200',
        'fixed_assets' => '1500',
        'accumulated_depreciation' => '1510',
        'payables' => '2000',
        'vat_payable' => '2100',
        'customer_deposits' => '2200',
        'owners_equity' => '3000',
        'retained_earnings' => '3100',
        'water_revenue' => '4000',
        'connection_fees' => '4100',
        'penalties' => '4200',
        'rental_income' => '4300',
        'service_charges' => '4400',
        'water_purchases' => '5000',
        'salaries' => '5100',
        'maintenance' => '5200',
        'bad_debt' => '5300',
        'depreciation' => '5400',
        'other_expenses' => '5900',
    ],

    /* Payment method => account that receives the money (PaymentReceived). */
    'payment_method_accounts' => [
        'cash' => '1000',
        'mpesa' => '1000',
        'bank' => '1000',
        'stripe' => '1000',
        'other' => '1000',
    ],

    /*
     * Invoice line category => revenue account (InvoiceIssued / CreditNoteIssued).
     * Lines without a category, or with an unlisted one, go to water_revenue.
     */
    'invoice_item_accounts' => [
        'water' => '4000',
        'rent' => '4300',
        'service_charge' => '4400',
    ],

    /* Expense category slug => expense account. Unlisted slugs use other_expenses. */
    'expense_category_accounts' => [
        'water-purchase' => '5000',
        'salaries' => '5100',
        'repairs' => '5200',
        'meter-fittings' => '5200',
    ],

    /* Code ranges treated as cash in the cash-flow statement (inclusive). */
    'cash_code_range' => ['1000', '1099'],
];
