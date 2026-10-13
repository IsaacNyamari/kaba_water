<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reading;

class ClientAnalytics
{
    public function __construct(protected int $clientId) {}

    public function consumptionTrend(int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = Reading::whereHas('meter', fn ($q) => $q->where('client_id', $this->clientId))
            ->where('status', 'approved')
            ->where('read_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(read_at, '%Y-%m') as ym, SUM(consumption) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $labels[] = $d->format('M Y');
            $values[] = (int) ($rows[$d->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function paymentTrend(int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = Payment::where('client_id', $this->clientId)
            ->where('paid_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $labels[] = $d->format('M Y');
            $values[] = round((float) ($rows[$d->format('Y-m')] ?? 0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}