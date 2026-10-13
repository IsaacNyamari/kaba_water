<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Enums\NormalSide;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Services\Accounting\Exceptions\InvalidAccountException;
use App\Services\Accounting\Exceptions\UnbalancedBalanceSheetException;
use App\Support\Money\MoneyMath;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Financial statements built from POSTED journal lines only.
 *
 * Every amount is a 2-dp decimal string computed with brick/money.
 * Sign conventions: "net debit" (Σdr − Σcr) is positive for debit-heavy
 * balances. Statements present each section on its natural side.
 */
class ReportService
{
    /**
     * Trial balance at $asOf: each account's cumulative balance shown in the
     * debit or credit column. Total debits always equal total credits because
     * every posted entry balances.
     *
     * @return array{as_of: CarbonInterface, rows: list<array>, total_debit: string, total_credit: string, balanced: bool}
     */
    public function trialBalance(int $vendorId, CarbonInterface $asOf): array
    {
        $totals = $this->totals($vendorId, null, $asOf);
        $rows = [];

        foreach ($this->accounts($vendorId) as $a) {
            $t = $totals->get($a->id);
            if (! $t) {
                continue; // no activity
            }

            $net = MoneyMath::sub($t['dr'], $t['cr']);

            $rows[] = [
                'account_id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type->value,
                'debit' => MoneyMath::isPositive($net) ? $net : '0.00',
                'credit' => MoneyMath::isNegative($net) ? MoneyMath::negate($net) : '0.00',
            ];
        }

        $dr = MoneyMath::sumColumn($rows, 'debit');
        $cr = MoneyMath::sumColumn($rows, 'credit');

        return [
            'as_of' => $asOf,
            'rows' => $rows,
            'total_debit' => $dr,
            'total_credit' => $cr,
            'balanced' => MoneyMath::equals($dr, $cr),
        ];
    }

    /**
     * Income statement (P&L) for a period.
     *   Revenue  = Σ(credit − debit) on revenue accounts
     *   Expenses = Σ(debit − credit) on expense accounts
     *   Net income = Revenue − Expenses
     *
     * @return array{from: CarbonInterface, to: CarbonInterface, revenue: list<array>, expenses: list<array>,
     *               total_revenue: string, total_expenses: string, net_income: string}
     */
    public function incomeStatement(int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $totals = $this->totals($vendorId, $from, $to);
        $revenue = [];
        $expenses = [];

        foreach ($this->accounts($vendorId) as $a) {
            $t = $totals->get($a->id);
            if (! $t) {
                continue;
            }

            if ($a->type === AccountType::Revenue) {
                $revenue[] = $this->row($a, MoneyMath::sub($t['cr'], $t['dr']));
            } elseif ($a->type === AccountType::Expense) {
                $expenses[] = $this->row($a, MoneyMath::sub($t['dr'], $t['cr']));
            }
        }

        $totalRevenue = MoneyMath::sumColumn($revenue, 'amount');
        $totalExpenses = MoneyMath::sumColumn($expenses, 'amount');

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_income' => MoneyMath::sub($totalRevenue, $totalExpenses),
        ];
    }

    /**
     * Balance sheet at $asOf.
     *   Assets      = Σ(debit − credit) on asset accounts (contra-assets show negative)
     *   Liabilities = Σ(credit − debit)
     *   Equity      = Σ(credit − debit) on equity accounts + current earnings
     * Current earnings = cumulative revenue − expenses to $asOf (no closing
     * entries are posted, so profit stays in the income accounts).
     *
     * @throws UnbalancedBalanceSheetException when Assets ≠ Liabilities + Equity
     */
    public function balanceSheet(int $vendorId, CarbonInterface $asOf): array
    {
        $totals = $this->totals($vendorId, null, $asOf);
        $assets = $liabilities = $equity = [];
        $earnings = MoneyMath::zero();

        foreach ($this->accounts($vendorId) as $a) {
            $t = $totals->get($a->id);
            if (! $t) {
                continue;
            }

            $netDebit = MoneyMath::sub($t['dr'], $t['cr']);
            $netCredit = MoneyMath::negate($netDebit);

            match ($a->type) {
                AccountType::Asset => $assets[] = $this->row($a, $netDebit),
                AccountType::Liability => $liabilities[] = $this->row($a, $netCredit),
                AccountType::Equity => $equity[] = $this->row($a, $netCredit),
                // Revenue (credit) adds to earnings, expense (debit) reduces them.
                AccountType::Revenue, AccountType::Expense => $earnings = $earnings->plus(MoneyMath::of($netCredit)),
            };
        }

        $currentEarnings = MoneyMath::str($earnings);
        $equity[] = ['account_id' => null, 'code' => '', 'name' => 'Current earnings (profit to date)', 'type' => 'equity', 'amount' => $currentEarnings];

        $totalAssets = MoneyMath::sumColumn($assets, 'amount');
        $totalLiabilities = MoneyMath::sumColumn($liabilities, 'amount');
        $totalEquity = MoneyMath::sumColumn($equity, 'amount');
        $liabPlusEquity = MoneyMath::add($totalLiabilities, $totalEquity);

        if (! MoneyMath::equals($totalAssets, $liabPlusEquity)) {
            throw new UnbalancedBalanceSheetException(
                "Assets ({$totalAssets}) ≠ Liabilities + Equity ({$liabPlusEquity}). The ledger contains an unbalanced entry — check the trial balance."
            );
        }

        return [
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'current_earnings' => $currentEarnings,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $liabPlusEquity,
        ];
    }

    /**
     * Cash-flow statement, indirect method, for a period.
     *
     *   Operating = net income
     *             + non-cash adjustments (increase in contra-assets, e.g. accumulated depreciation)
     *             − increase in other non-cash assets (receivables, inventory…)
     *             + increase in liabilities (payables, VAT, deposits…)
     *   Investing = − increase in fixed assets (codes 1500–1599 with a debit normal side)
     *   Financing = + increase in equity accounts (owner contributions, opening balances)
     *
     * Because every entry balances, Σ all accounts' movements = 0, so
     * operating + investing + financing always equals the change in cash.
     */
    public function cashFlow(int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $period = $this->totals($vendorId, $from, $to);
        $before = $this->totals($vendorId, null, $from->copy()->subDay());
        $toEnd = $this->totals($vendorId, null, $to);

        $netIncome = MoneyMath::zero();
        $adjustments = $workingCapital = $investing = $financing = [];
        $opening = MoneyMath::zero();
        $closing = MoneyMath::zero();

        foreach ($this->accounts($vendorId) as $a) {
            if ($this->isCash($a)) {
                $b = $before->get($a->id);
                $e = $toEnd->get($a->id);
                $opening = $opening->plus(MoneyMath::of($b ? MoneyMath::sub($b['dr'], $b['cr']) : '0'));
                $closing = $closing->plus(MoneyMath::of($e ? MoneyMath::sub($e['dr'], $e['cr']) : '0'));

                continue;
            }

            $t = $period->get($a->id);
            if (! $t) {
                continue;
            }

            // Cash effect of a non-cash account's movement = −(Δ net debit).
            $effect = MoneyMath::sub($t['cr'], $t['dr']);
            if (MoneyMath::isZero($effect)) {
                continue;
            }

            if ($a->type->isIncomeStatement()) {
                $netIncome = $netIncome->plus(MoneyMath::of($effect));
            } elseif ($a->type === AccountType::Equity) {
                $financing[] = $this->row($a, $effect);
            } elseif ($a->type === AccountType::Asset && $a->normal_side === NormalSide::Credit) {
                $adjustments[] = $this->row($a, $effect); // e.g. accumulated depreciation
            } elseif ($a->type === AccountType::Asset && $this->isFixedAsset($a)) {
                $investing[] = $this->row($a, $effect);
            } else {
                $workingCapital[] = $this->row($a, $effect);
            }
        }

        $netIncomeStr = MoneyMath::str($netIncome);
        $operating = MoneyMath::add($netIncomeStr, MoneyMath::sumColumn($adjustments, 'amount'), MoneyMath::sumColumn($workingCapital, 'amount'));
        $investingTotal = MoneyMath::sumColumn($investing, 'amount');
        $financingTotal = MoneyMath::sumColumn($financing, 'amount');
        $netChange = MoneyMath::add($operating, $investingTotal, $financingTotal);
        $openingStr = MoneyMath::str($opening);
        $closingStr = MoneyMath::str($closing);

        return [
            'from' => $from,
            'to' => $to,
            'net_income' => $netIncomeStr,
            'adjustments' => $adjustments,
            'working_capital' => $workingCapital,
            'operating' => $operating,
            'investing' => $investing,
            'investing_total' => $investingTotal,
            'financing' => $financing,
            'financing_total' => $financingTotal,
            'net_change' => $netChange,
            'opening_cash' => $openingStr,
            'closing_cash' => $closingStr,
            'ties' => MoneyMath::equals($netChange, MoneyMath::sub($closingStr, $openingStr)),
        ];
    }

    /**
     * General ledger for one account: opening balance, every posted line in
     * the period with a running balance (on the account's normal side), and
     * the closing balance.
     */
    public function generalLedger(int $vendorId, int $accountId, CarbonInterface $from, CarbonInterface $to): array
    {
        $account = ChartOfAccount::withoutGlobalScope('vendor')->withTrashed()
            ->where('vendor_id', $vendorId)->find($accountId);

        if (! $account) {
            throw new InvalidAccountException('That account does not belong to this vendor.');
        }

        $debitNormal = $account->normal_side === NormalSide::Debit;
        $signed = fn (string $dr, string $cr) => $debitNormal ? MoneyMath::sub($dr, $cr) : MoneyMath::sub($cr, $dr);

        $openingRow = $this->totals($vendorId, null, $from->copy()->subDay())->get($account->id);
        $opening = $openingRow ? $signed($openingRow['dr'], $openingRow['cr']) : '0.00';

        $lines = JournalLine::query()
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->where('je.vendor_id', $vendorId)
            ->whereNotNull('je.posted_at')
            ->where('journal_lines.account_id', $account->id)
            ->whereDate('je.date', '>=', $from->toDateString())
            ->whereDate('je.date', '<=', $to->toDateString())
            ->orderBy('je.date')->orderBy('je.id')->orderBy('journal_lines.id')
            ->get([
                'journal_lines.id', 'journal_lines.debit', 'journal_lines.credit', 'journal_lines.description',
                'je.id as entry_id', 'je.date as entry_date', 'je.reference', 'je.memo',
            ]);

        $running = MoneyMath::of($opening);
        $rows = [];

        foreach ($lines as $l) {
            $dr = MoneyMath::str($this->dec($l->debit));
            $cr = MoneyMath::str($this->dec($l->credit));
            $running = $running->plus(MoneyMath::of($signed($dr, $cr)));

            $rows[] = [
                'entry_id' => (int) $l->entry_id,
                'date' => substr((string) $l->entry_date, 0, 10),
                'reference' => $l->reference,
                'description' => $l->description ?: $l->memo,
                'debit' => $dr,
                'credit' => $cr,
                'balance' => MoneyMath::str($running),
            ];
        }

        return [
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'rows' => $rows,
            'total_debit' => MoneyMath::sumColumn($rows, 'debit'),
            'total_credit' => MoneyMath::sumColumn($rows, 'credit'),
            'closing' => MoneyMath::str($running),
        ];
    }

    // ── internals ──────────────────────────────────────────

    /** @return Collection<int, ChartOfAccount> */
    protected function accounts(int $vendorId): Collection
    {
        return ChartOfAccount::withoutGlobalScope('vendor')->withTrashed()
            ->where('vendor_id', $vendorId)
            ->orderBy('code')
            ->get();
    }

    /**
     * Σ debit / Σ credit per account on posted entries, optionally bounded.
     *
     * @return Collection<int, array{dr: string, cr: string}> keyed by account_id
     */
    protected function totals(int $vendorId, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        return DB::table('journal_lines')
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->where('je.vendor_id', $vendorId)
            ->whereNotNull('je.posted_at')
            ->when($from, fn ($q) => $q->whereDate('je.date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('je.date', '<=', $to->toDateString()))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id as account_id, SUM(journal_lines.debit) as dr, SUM(journal_lines.credit) as cr')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->account_id => [
                'dr' => MoneyMath::str($this->dec($r->dr)),
                'cr' => MoneyMath::str($this->dec($r->cr)),
            ]]);
    }

    /** DB drivers may hand SUM() back as float (SQLite) — normalise to a string without float maths. */
    protected function dec(mixed $v): string
    {
        if ($v === null) {
            return '0';
        }
        if (is_float($v)) {
            return sprintf('%.4F', $v);
        }

        return (string) $v;
    }

    protected function row(ChartOfAccount $a, string $amount): array
    {
        return ['account_id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type->value, 'amount' => $amount];
    }

    protected function isCash(ChartOfAccount $a): bool
    {
        [$lo, $hi] = config('accounting.cash_code_range', ['1000', '1099']);

        return $a->type === AccountType::Asset && is_numeric($a->code)
            && (int) $a->code >= (int) $lo && (int) $a->code <= (int) $hi;
    }

    protected function isFixedAsset(ChartOfAccount $a): bool
    {
        return is_numeric($a->code) && (int) $a->code >= 1500 && (int) $a->code <= 1599;
    }
}
