# Financial reports

All reports read **posted** journal lines only, are scoped to the signed-in vendor, filter by date,
and export to XLSX via the **Export** button. Amounts are exact 2-dp decimals (`brick/money`).
Code: `App\Services\Accounting\ReportService`.

## Trial balance — *as of a date*
Each account's cumulative balance (Σdr − Σcr), shown in the debit column when positive and the credit
column when negative. **Total debits always equal total credits** because every posted entry balances;
the page warns if they ever don't.

## Income statement — *from / to*
* Revenue = Σ(credit − debit) on revenue accounts
* Expenses = Σ(debit − credit) on expense accounts
* **Net income = Revenue − Expenses**

*Compare with prior period* shows the same-length period immediately before (e.g. March vs February).

## Balance sheet — *as of a date*
* Assets = Σ(debit − credit) on asset accounts — contra-assets such as 1510 Accumulated Depreciation
  appear as negatives
* Liabilities and equity = Σ(credit − debit)
* **Current earnings** = cumulative revenue − expenses to the date (no closing entries are posted)

The service asserts **Assets = Liabilities + Equity** and throws `UnbalancedBalanceSheetException`
otherwise; the page then shows a clear error instead of numbers. With entries posted through
`LedgerService` this cannot happen — it would indicate data inserted around the ledger.

## Cash flow — indirect method, *from / to*
```
Operating = Net income
          + increase in contra-assets (depreciation — non-cash)
          − increase in other non-cash assets (receivables, inventory…)
          + increase in liabilities (payables, VAT, deposits…)
Investing = − increase in fixed assets (codes 1500–1599, debit normal side)
Financing = + increase in equity accounts (owner capital, opening balances)
Net change = Operating + Investing + Financing  =  closing cash − opening cash
```
Cash accounts are codes `1000–1099` (`config/accounting.php` → `cash_code_range`). Because every
non-cash movement is classified somewhere and entries balance, the statement always **ties** to the
change in Cash / Bank; the page shows a badge confirming it.

## General ledger — *account, from / to*
Opening balance (before *from*), every posted line with entry number, reference and description, and a
running balance on the account's **normal side** (positive = normal balance), then totals and the
closing balance. Click an entry number to open it.

## Journal entries
List of posted entries with date, search (#id, reference, memo) and source filters. Export is one
row per line (entry #, date, reference, memo, account code/name, debit, credit).

## Common pitfalls
* **Draft invoices aren't in revenue.** They post when sent.
* **Opening balances + backfill:** documents dated on/before the opening date are skipped by the
  backfill so they aren't counted twice.
* **Back-dated documents into a closed period are refused** — reopen the period (logged) or use a later date.
* **Deactivating an account** blocks new postings to it; reports still include its history.
* **SQLite vs MySQL:** reports are portable; the older analytics dashboards now use
  `App\Support\SqlDate` so they also run on SQLite (tests).
