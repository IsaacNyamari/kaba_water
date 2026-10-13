<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Asset;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Liability;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class FinancialReports
{
    public function __construct(protected int $vendorId) {}

    /**
     * Income statement for a period.
     *
     * @return array{
     *   period: array{from: CarbonInterface, to: CarbonInterface},
     *   revenue: float,
     *   expense_lines: array<int, array{name: string, color: string, amount: float}>,
     *   expenses: float,
     *   net_profit: float,
     *   profit_margin: float,
     * }
     */
    public function incomeStatement(CarbonInterface $from, CarbonInterface $to): array
    {
        $revenue = (float) Payment::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $expenseRows = Expense::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('expense_category_id, SUM(amount + tax) as total')
            ->groupBy('expense_category_id')
            ->with('category:id,name,color')
            ->get();

        $expenseTotal = $expenseRows->sum('total');

        return [
            'period'        => ['from' => $from, 'to' => $to],
            'revenue'       => round($revenue, 2),
            'expense_lines' => $expenseRows->map(fn($e) => [
                'name'   => $e->category?->name ?? 'Uncategorised',
                'color'  => $e->category?->color ?? 'zinc',
                'amount' => round((float) $e->total, 2),
            ])->all(),
            'expenses'      => round($expenseTotal, 2),
            'net_profit'    => round($revenue - $expenseTotal, 2),
            'profit_margin' => $revenue > 0
                ? round((($revenue - $expenseTotal) / $revenue) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Simplified cash-basis balance sheet as of a date.
     *
     * @return array{
     *   as_of: CarbonInterface,
     *   cash: float,
     *   receivables: float,
     *   fixed_assets: float,
     *   total_assets: float,
     *   liability_lines: array<int, array{name: string, amount: float}>,
     *   total_liabilities: float,
     *   equity: float,
     * }
     */
    public function balanceSheet(?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ?? now();

        // ── Assets ──────────────────────────────────────────────
        $cashIn = (float) Payment::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->where('paid_at', '<=', $asOf)
            ->sum('amount');

        $cashOut = (float) Expense::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->where('paid_at', '<=', $asOf)
            ->sum(DB::raw('amount + tax'));

        // Cash on hand: derived from tracked transactions. Adjust if you
        // maintain a real opening-balance row elsewhere.
        $cash = round($cashIn - $cashOut, 2);

        $receivables = (float) (Invoice::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->whereIn('status', [
                InvoiceStatus::Sent->value,
                InvoiceStatus::Overdue->value,
            ])
            ->where('issued_at', '<=', $asOf)
            ->selectRaw('SUM(total - amount_paid) as bal')
            ->value('bal') ?? 0);

        $fixedAssets = Asset::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->where('status', 'active')
            ->get()
            ->sum(fn(Asset $a) => $a->bookValue());

        $totalAssets = round($cash + $receivables + $fixedAssets, 2);

        // ── Liabilities ─────────────────────────────────────────
        $liabilities = Liability::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->where('status', 'active')
            ->get();

        $liabilityLines = $liabilities->map(fn(Liability $l) => [
            'name'   => $l->name,
            'amount' => round($l->outstanding(), 2),
        ])->all();

        $totalLiabilities = round(collect($liabilityLines)->sum('amount'), 2);

        return [
            'as_of'             => $asOf,
            'cash'              => $cash,
            'receivables'       => round($receivables, 2),
            'fixed_assets'      => round((float) $fixedAssets, 2),
            'total_assets'      => $totalAssets,
            'liability_lines'   => $liabilityLines,
            'total_liabilities' => $totalLiabilities,
            'equity'            => round($totalAssets - $totalLiabilities, 2),
        ];
    }

    /**
     * Monthly revenue vs. expenses for the last N months.
     *
     * @return array{labels: array<int, string>, rev: array<int, float>, exp: array<int, float>, net: array<int, float>}
     */
    public function cashFlowTrend(int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $revenue = Payment::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->where('paid_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $expenses = Expense::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->where('paid_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount + tax) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = [];
        $rev    = [];
        $exp    = [];
        $net    = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $d  = now()->subMonths($i);
            $ym = $d->format('Y-m');

            $r = (float) ($revenue[$ym] ?? 0);
            $e = (float) ($expenses[$ym] ?? 0);

            $labels[] = $d->format('M Y');
            $rev[]    = round($r, 2);
            $exp[]    = round($e, 2);
            $net[]    = round($r - $e, 2);
        }

        return compact('labels', 'rev', 'exp', 'net');
    }

    /**
     * Expenses by category for a period.
     *
     * @return array{labels: array<int, string>, values: array<int, float>, colors: array<int, string>}
     */
    public function expensesByCategory(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Expense::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('expense_category_id, SUM(amount + tax) as total')
            ->groupBy('expense_category_id')
            ->with('category:id,name,color')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('category.name')->all(),
            'values' => $rows->map(fn($r) => round((float) $r->total, 2))->all(),
            'colors' => $rows->pluck('category.color')->all(),
        ];
    }
}
