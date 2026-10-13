<?php

namespace Database\Seeders;

use App\Models\JournalEntry;
use App\Models\Vendor;
use App\Services\Accounting\LedgerService;
use App\Support\Money\MoneyMath;
use Illuminate\Database\Seeder;

/**
 * Three months of realistic ledger activity for the demo vendor so the
 * reports aren't empty on first install. Skipped when the demo vendor
 * already has journal entries (idempotent).
 */
class DemoAccountingSeeder extends Seeder
{
    public function run(LedgerService $ledger): void
    {
        $vendor = Vendor::where('slug', 'demo-water')->first();

        if (! $vendor || JournalEntry::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->exists()) {
            return;
        }

        $post = function (string $date, string $memo, array $lines) use ($ledger, $vendor) {
            $ledger->post(
                array_map(fn ($l) => ['account_code' => $l[0], 'debit' => $l[1] ?? '0', 'credit' => $l[2] ?? '0'], $lines),
                ['vendor_id' => $vendor->id, 'date' => $date, 'reference' => 'DEMO', 'memo' => $memo],
            );
        };

        $start = now()->subMonths(2)->startOfMonth();

        // Owner puts in capital and buys meters / pumps.
        $post($start->toDateString(), "Owner's capital contribution", [['1000', '500000.00'], ['3000', null, '500000.00']]);
        $post($start->copy()->addDays(2)->toDateString(), 'Purchase of meters and pump', [['1500', '240000.00'], ['1000', null, '240000.00']]);

        foreach ([0, 1, 2] as $m) {
            $month = $start->copy()->addMonths($m);
            $billed = MoneyMath::str((string) (180000 + $m * 15000)); // net water sales
            $vat = MoneyMath::multiply($billed, '0.16');
            $gross = MoneyMath::add($billed, $vat);

            $post($month->copy()->day(5)->toDateString(), "Water bills — {$month->format('M Y')}", [
                ['1100', $gross], ['4000', null, $billed], ['2100', null, $vat],
            ]);
            $post($month->copy()->day(8)->toDateString(), "Connection fees — {$month->format('M Y')}", [
                ['1000', '12000.00'], ['4100', null, '12000.00'],
            ]);
            $post($month->copy()->day(20)->toDateString(), "Customer payments — {$month->format('M Y')}", [
                ['1000', MoneyMath::multiply($gross, '0.85')], ['1100', null, MoneyMath::multiply($gross, '0.85')],
            ]);
            $post($month->copy()->day(3)->toDateString(), "Bulk water purchase — {$month->format('M Y')}", [
                ['5000', '65000.00'], ['2000', null, '65000.00'],
            ]);
            $post($month->copy()->day(25)->toDateString(), "Supplier payment — {$month->format('M Y')}", [
                ['2000', '60000.00'], ['1000', null, '60000.00'],
            ]);
            $post($month->copy()->day(28)->toDateString(), "Salaries — {$month->format('M Y')}", [
                ['5100', '48000.00'], ['1000', null, '48000.00'],
            ]);
            $post($month->copy()->day(15)->toDateString(), "Pipe & meter repairs — {$month->format('M Y')}", [
                ['5200', (string) (6500 + $m * 1200).'.00'], ['1000', null, (string) (6500 + $m * 1200).'.00'],
            ]);
            $post($month->copy()->endOfMonth()->toDateString(), "Depreciation — {$month->format('M Y')}", [
                ['5400', '4000.00'], ['1510', null, '4000.00'],
            ]);
        }

        // One small bad debt.
        $post($start->copy()->addMonths(2)->day(27)->toDateString(), 'Bad debt written off — demo customer', [
            ['5300', '3480.00'], ['1100', null, '3480.00'],
        ]);
    }
}
