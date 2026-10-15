<?php

namespace App\Services\Metering;

use App\Enums\ReadingStatus;
use App\Models\Integration;
use App\Models\Meter;
use App\Models\MeterTelemetry;
use App\Models\Reading;
use Carbon\CarbonInterface;

/**
 * Turns smart-meter telemetry into the monthly billing reading: for each
 * postpaid smart meter without a reading that month, the last value it
 * reported in the month becomes the reading (source "smart_meter"). The
 * vendor chooses whether these are approved straight away or wait for review.
 */
class SmartMeterReadings
{
    /** @return int readings created */
    public function createFor(int $vendorId, CarbonInterface $month): int
    {
        $integration = Integration::active($vendorId, Integration::SMART_METERS);
        if (! $integration) {
            return 0;
        }

        $status = $integration->setting('auto_approve') ? ReadingStatus::Approved->value : ReadingStatus::Pending->value;
        $end = $month->copy()->endOfMonth();
        $created = 0;

        Meter::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->whereNotNull('device_id')
            ->where('billing_mode', 'postpaid')
            ->where('status', 'active')
            ->each(function (Meter $meter) use ($month, $end, $status, &$created) {
                if (Reading::inMonthFor($meter->id, $month)) {
                    return;
                }

                $last = MeterTelemetry::withoutGlobalScope('vendor')
                    ->where('meter_id', $meter->id)
                    ->whereBetween('read_at', [$month->copy()->startOfMonth(), $end])
                    ->orderByDesc('read_at')
                    ->first();

                if (! $last) {
                    return;
                }

                $value = (int) floor((float) (string) $last->value);
                $previous = Reading::lastApprovedValueFor($meter->id, $last->read_at);

                // A meter that was replaced or reset reports less than before — leave it for a person.
                if ($previous !== null && $value < $previous) {
                    return;
                }

                Reading::withoutGlobalScope('vendor')->create([
                    'vendor_id' => $meter->vendor_id,
                    'meter_id' => $meter->id,
                    'reading' => $value,
                    'source' => 'smart_meter',
                    'status' => $status,
                    'read_at' => $last->read_at,
                ]);

                $created++;
            });

        return $created;
    }
}
