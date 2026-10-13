<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reading;
use Illuminate\Support\Facades\DB;

class VendorAnalytics
{
    public function __construct(protected int $vendorId) {}

    /**
     * Revenue and payments for the last N months.
     */
    public function revenueTrend(int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $payments = Payment::withoutGlobalScope('vendor')
            ->where('payments.vendor_id', $this->vendorId)
            ->where('payments.paid_at', '>=', $start)
            ->selectRaw(\App\Support\SqlDate::yearMonth('payments.paid_at') . " as ym, SUM(payments.amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $invoices = Invoice::withoutGlobalScope('vendor')
            ->where('invoices.vendor_id', $this->vendorId)
            ->where('invoices.status', '!=', InvoiceStatus::Void->value)
            ->where('invoices.issued_at', '>=', $start)
            ->selectRaw(\App\Support\SqlDate::yearMonth('invoices.issued_at') . " as ym, SUM(invoices.total) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = $revenue = $billed = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $ym   = $date->format('Y-m');

            $labels[]  = $date->format('M Y');
            $revenue[] = round((float) ($payments[$ym] ?? 0), 2);
            $billed[]  = round((float) ($invoices[$ym] ?? 0), 2);
        }

        return compact('labels', 'revenue', 'billed');
    }

    /**
     * Total consumption (m³) per month across all meters.
     */
    public function consumptionTrend(int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = Reading::withoutGlobalScope('vendor')
            ->where('readings.vendor_id', $this->vendorId)
            ->where('readings.status', 'approved')
            ->where('readings.read_at', '>=', $start)
            ->selectRaw(\App\Support\SqlDate::yearMonth('readings.read_at') . " as ym, SUM(readings.consumption) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');
            $values[] = (int) ($rows[$date->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Invoice status breakdown.
     */
    public function invoiceStatusMix(): array
    {
        $rows = Invoice::withoutGlobalScope('vendor')
            ->where('invoices.vendor_id', $this->vendorId)
            ->selectRaw('invoices.status as status, COUNT(*) as c')
            ->groupBy('invoices.status')
            ->get();

        $map = ['draft' => 0, 'sent' => 0, 'paid' => 0, 'overdue' => 0, 'void' => 0, 'written_off' => 0];

        foreach ($rows as $r) {
            $key = is_object($r->status) ? $r->status->value : $r->status;
            $map[$key] = (int) $r->c;
        }

        return [
            'labels' => ['Draft', 'Sent', 'Paid', 'Overdue', 'Void', 'Written off'],
            'values' => array_values($map),
        ];
    }

    /**
     * Top N consumers by approved consumption.
     *
     * Uses a query builder (not Eloquent) to avoid the global scope
     * adding an unqualified `vendor_id = ?` that collides with joined tables.
     */
    public function topConsumers(int $limit = 10, ?string $from = null, ?string $to = null): array
    {
        return DB::table('readings')
            ->join('meters',  'meters.id',  '=', 'readings.meter_id')
            ->join('clients', 'clients.id', '=', 'meters.client_id')
            ->where('readings.vendor_id', $this->vendorId)
            ->where('readings.status', 'approved')
            ->when($from, fn($q) => $q->whereDate('readings.read_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('readings.read_at', '<=', $to))
            ->groupBy('clients.id', 'clients.name', 'clients.account_no')
            ->selectRaw('clients.id as id, clients.name as name, clients.account_no as account_no, SUM(readings.consumption) as total')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'id'         => (int) $r->id,
                'name'       => $r->name,
                'account_no' => $r->account_no,
                'total'      => (int) $r->total,
            ])
            ->all();
    }

    /**
     * Receivables aging buckets.
     */
    public function agingBuckets(): array
    {
        $rows = Invoice::withoutGlobalScope('vendor')
            ->where('invoices.vendor_id', $this->vendorId)
            ->whereIn('invoices.status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->get(['invoices.due_at', 'invoices.total', 'invoices.amount_paid']);

        $buckets = [
            'Current'    => 0,
            '1-30 days'  => 0,
            '31-60 days' => 0,
            '61-90 days' => 0,
            '90+ days'   => 0,
        ];

        foreach ($rows as $r) {
            $balance = (float) $r->total - (float) $r->amount_paid;
            $days    = now()->diffInDays($r->due_at, false);

            if ($days >= 0)         $buckets['Current']    += $balance;
            elseif ($days >= -30)   $buckets['1-30 days']  += $balance;
            elseif ($days >= -60)   $buckets['31-60 days'] += $balance;
            elseif ($days >= -90)   $buckets['61-90 days'] += $balance;
            else                    $buckets['90+ days']   += $balance;
        }

        return [
            'labels' => array_keys($buckets),
            'values' => array_map(fn($v) => round($v, 2), array_values($buckets)),
        ];
    }
}
