# Accounting module

A double-entry ledger built into Kaba Water. Every vendor (workspace) has its own
chart of accounts, journal, fiscal periods and statements; nothing is shared between vendors.

## Core rules (enforced in `App\Services\Accounting\LedgerService`)

| Rule | Where |
|---|---|
| Every entry has ≥ 2 lines; a line is a debit **or** a credit | `LedgerService::post()` (+ a DB `CHECK` on MySQL 8.0.16+) |
| Σ debits = Σ credits exactly | `LedgerService::assertBalanced()` |
| Money is never a float — `brick/money`, `DECIMAL(15,2)` | `App\Support\Money\MoneyMath` (rejects floats) |
| Accounts must belong to the entry's vendor and be active | `LedgerService::normalizeLines()` |
| No entry may be dated inside a closed fiscal period | `FiscalPeriodService::assertOpen()` |
| Posted entries are never edited or deleted — only reversed | `JournalEntry` / `JournalLine` model hooks |
| Auto-postings are idempotent | `source_type` + `source_id` + `source_event` lookup in `LedgerService::postEvent()` |

## Chart of accounts

Seeded per vendor by `ChartOfAccountsService::seedDefaultsFor()` — on vendor creation
(`VendorObserver`), on first use of any accounting page, by `ChartOfAccountsSeeder`, and lazily
by `LedgerService`. Seeded accounts are `is_system`: their code, type and normal side are locked and
they can be deactivated but not deleted.

| Code | Name | Type | Normal |
|---|---|---|---|
| 1000 | Cash / Bank | asset | debit |
| 1100 | Accounts Receivable | asset | debit |
| 1200 | Inventory | asset | debit |
| 1500 | Meters & Equipment | asset | debit |
| 1510 | Accumulated Depreciation | asset (contra) | credit |
| 2000 | Accounts Payable | liability | credit |
| 2100 | VAT Payable | liability | credit |
| 2200 | Customer Deposits | liability | credit |
| 3000 | Owner's Equity | equity | credit |
| 3100 | Retained Earnings | equity | credit |
| 4000 | Water Revenue | revenue | credit |
| 4100 | Connection Fees | revenue | credit |
| 4200 | Penalties & Fees | revenue | credit |
| 5000 | Water Purchases | expense | debit |
| 5100 | Salaries | expense | debit |
| 5200 | Maintenance | expense | debit |
| 5300 | Bad Debt | expense | debit |
| 5400 | Depreciation Expense | expense | debit |
| 5900 | Other Operating Expenses | expense | debit |

`5900` is an addition to the original spec: rent, electricity, office costs etc. need somewhere to go.
Vendors can add their own accounts (e.g. `1010 M-Pesa till`) next to these.

## Posting rules

Rules live in `app/Services/Accounting/Postings/` and implement `PostableEvent` (`lines()` + `meta()`).
Account codes come from `config/accounting.php`, so a mapping can change without code changes.

| Event | Trigger | Debit | Credit |
|---|---|---|---|
| `InvoiceIssued` | invoice leaves **draft** (sent / overdue / paid) | 1100 total | 4000 net, 2100 tax |
| `CreditNoteIssued` | an issued invoice is **voided** | 4000 net, 2100 tax | 1100 total |
| *(reversal)* | an issued invoice is deleted | mirror of the issue entry | |
| `PaymentReceived` | payment created | 1000 (per method, `payment_method_accounts`) | 1100 |
| *(reversal)* | payment deleted | 1100 | 1000 |
| `ExpenseRecorded` | expense created (category → `expense_category_accounts`, else 5900) | expense account (amount + tax) | 1000 (or 2000 on credit) |
| `WaterPurchase` | expense in category `water-purchase` | 5000 | 1000 / 2000 |
| `SalaryPaid` | expense in category `salaries` | 5100 | 1000 |
| *(reverse + re-post)* | expense amount / tax / category / date / method edited | | |
| `BadDebtWrittenOff` | **Write off** on an invoice | 5300 unpaid balance | 1100 |
| `DepreciationPosted` | **Post depreciation** on Assets (monthly, once per asset) | 5400 | 1510 |
| `OpeningBalanceEntry` | Opening Balances wizard (once per vendor) | 1000, 1100, 1500 | 1510, 2000, 3000 (plug) |

**Why invoices post on issue, not on creation:** a draft can still be edited or deleted and is not yet
owed by the customer. Posting drafts would put revenue on the books that may never exist.

Observers (`app/Observers/`) are registered in `AppServiceProvider::boot()`. Each checks the fiscal
period **before** the row is saved, so a document dated in a closed period is refused rather than
saved without its ledger entry.

### Adding a new posting rule

1. Create a class in `app/Services/Accounting/Postings/` extending `Posting`.
2. `lines()` returns balanced lines using `self::dr(code, amount)` / `self::cr(code, amount)`;
   amounts are decimal strings — use `MoneyMath` for any arithmetic.
3. `meta()` returns `vendor_id`, `date`, `memo` and — for anything triggered automatically —
   `source_type`, `source_id` and a unique `source_event` so it can't double-post.
4. Post it with `app(LedgerService::class)->postEvent(new YourRule(...))`, from an observer or action.
5. Add a test in `tests/Unit/Accounting/PostingRulesTest.php` asserting the accounts and amounts.

## Fiscal periods & closing

* **Fiscal Periods** page → *Set up quarters* creates `YYYY-Q1…Q4` (custom periods are allowed; overlaps are refused).
* **Close** (`accounting.close-period`): no entry, reversal, payment, expense or invoice status change
  may be dated inside the period afterwards.
* **Reopen** (`accounting.reopen-period`) requires a reason and is written to `fiscal_period_events`
  with the user — the audit trail shown on the page.
* No closing entries are posted: the balance sheet shows profit to date as *Current earnings* in equity.

### Period-close checklist

1. All invoices for the period issued (no stray drafts); overdue job has run.
2. All payments and expenses for the period entered.
3. Depreciation posted for each month (Assets → *Post depreciation*).
4. Trial balance balances; review the general ledger for 1000 against bank / M-Pesa statements.
5. Receivables (1100) match the unpaid-invoices total; VAT payable (2100) matches the VAT return.
6. Export the statements (each report has **Export**), then close the period.

## Reconciliation checklist

* **Cash 1000** ↔ bank + M-Pesa statements (general ledger, date range = statement period).
* **Receivables 1100** ↔ Σ (total − amount paid) of sent/overdue invoices.
* **VAT 2100** ↔ VAT charged on issued invoices less credit notes.
* **Fixed assets 1500 / 1510** ↔ assets register cost / accumulated depreciation.
* **Payables 2000** ↔ supplier statements and the Liabilities register.

## Existing data

`php artisan accounting:backfill [--vendor=ID]` posts invoices (sent/overdue/paid), payments and
expenses that existed before the module. It is idempotent and skips anything dated on or before a
vendor's posted opening-balance date (those amounts are already in the opening balances).

## Roles & permissions

| Permission | vendor (owner) | accountant | vendor_staff |
|---|---|---|---|
| `accounting.view` | ✓ | ✓ | ✓ |
| `accounting.post` (manual entries, reversals, opening balances, chart edits, write-offs, depreciation) | ✓ | ✓ | |
| `accounting.close-period` | ✓ | ✓ | |
| `accounting.reopen-period` | ✓ | ✓ | |

Accountants and staff only see the Accounting section. Platform admins can grant these roles
when approving an access request. Permissions are Spatie permissions, which Laravel's Gate
resolves directly (there is no `AuthServiceProvider` in Laravel 11+).
