<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Services\Metering\SmartMeterReadings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Create the monthly billing readings of smart meters from their telemetry.
 * Scheduled on the 1st of each month (for the month just ended) in routes/console.php.
 */
class CreateSmartMeterReadings extends Command
{
    protected $signature = 'meters:smart-readings {--month= : Month to read, YYYY-MM (default: last month)}';

    protected $description = 'Create monthly readings for smart meters from the values they reported';

    public function handle(SmartMeterReadings $readings): int
    {
        $month = $this->option('month')
            ? CarbonImmutable::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $total = 0;

        Integration::withoutGlobalScope('vendor')
            ->where('type', Integration::SMART_METERS)
            ->where('is_enabled', true)
            ->pluck('vendor_id')
            ->each(function (int $vendorId) use ($readings, $month, &$total) {
                app()->instance('current_vendor_id', $vendorId);
                $total += $readings->createFor($vendorId, $month);
            });

        app()->forgetInstance('current_vendor_id');

        $this->info("{$total} smart meter reading(s) created for {$month->format('F Y')}.");

        return self::SUCCESS;
    }
}
