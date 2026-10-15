<?php

namespace App\Services\Metering;

use App\Models\Integration;
use App\Models\Meter;
use App\Models\MeterTelemetry;
use App\Support\Notifier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Takes readings pushed by smart water meters and stores them as telemetry.
 *
 * Accepts, on one webhook URL per vendor:
 *  - ChirpStack v4 HTTP integration uplinks (deviceInfo.devEui + decoded "object")
 *  - The Things Stack v3 webhooks (end_device_ids.dev_eui + uplink_message.decoded_payload)
 *  - Plain JSON from a meter maker's head-end system:
 *      {"readings": [{"device_id": "...", "value": 123.45, "read_at": "...", "battery": 87, "alerts": ["leak"]}]}
 *    (a single reading object without the "readings" wrapper also works; "meter_number" may replace "device_id")
 *
 * The decoded value is taken from the configured field (or the usual names)
 * and divided by the configured divisor (1000 for meters that count litres).
 */
class TelemetryIngest
{
    /** Field names meters commonly use for the cumulative index. */
    public const VALUE_FIELDS = ['reading', 'volume', 'total_volume', 'totalVolume', 'meter_reading', 'cumulative', 'index', 'value'];

    public const ALERTS = ['leak', 'burst', 'tamper', 'reverse_flow', 'low_battery', 'valve_closed', 'magnetic', 'dry_run'];

    /**
     * @return array{accepted: int, unknown: list<string>}
     */
    public function ingest(Integration $integration, array $payload): array
    {
        $accepted = 0;
        $unknown = [];

        foreach ($this->normalise($integration, $payload) as $row) {
            $meter = $this->findMeter($integration->vendor_id, $row['device_id'], $row['meter_number']);

            if (! $meter) {
                $unknown[] = (string) ($row['device_id'] ?? $row['meter_number']);

                continue;
            }

            if ($row['value'] === null) {
                $this->touch($meter, $row, null);

                continue;
            }

            MeterTelemetry::withoutGlobalScope('vendor')->updateOrCreate(
                ['meter_id' => $meter->id, 'read_at' => $row['read_at']],
                [
                    'vendor_id' => $meter->vendor_id,
                    'value' => $row['value'],
                    'battery_percent' => $row['battery'],
                    'signal' => $row['signal'],
                    'alerts' => $row['alerts'] ?: null,
                    'frame_id' => $row['frame_id'],
                    'payload' => $row['raw'],
                ],
            );

            $this->touch($meter, $row, $row['value']);
            $accepted++;
        }

        $integration->forceFill(['last_used_at' => now()])->save();

        return ['accepted' => $accepted, 'unknown' => array_values(array_unique(array_filter($unknown)))];
    }

    /**
     * @return list<array{device_id: ?string, meter_number: ?string, value: ?string, read_at: CarbonImmutable, battery: ?int, signal: ?int, alerts: list<string>, frame_id: ?string, raw: array}>
     */
    public function normalise(Integration $integration, array $payload): array
    {
        // ChirpStack v4
        if (isset($payload['deviceInfo']['devEui'])) {
            return [$this->row($integration, $payload['object'] ?? [], [
                'device_id' => $payload['deviceInfo']['devEui'],
                'read_at' => $payload['time'] ?? null,
                'frame_id' => isset($payload['fCnt']) ? (string) $payload['fCnt'] : ($payload['deduplicationId'] ?? null),
                'signal' => $payload['rxInfo'][0]['rssi'] ?? null,
            ], $payload)];
        }

        // The Things Stack v3
        if (isset($payload['end_device_ids']['dev_eui'])) {
            $uplink = $payload['uplink_message'] ?? [];

            return [$this->row($integration, $uplink['decoded_payload'] ?? [], [
                'device_id' => $payload['end_device_ids']['dev_eui'],
                'read_at' => $uplink['received_at'] ?? $payload['received_at'] ?? null,
                'frame_id' => isset($uplink['f_cnt']) ? (string) $uplink['f_cnt'] : null,
                'signal' => $uplink['rx_metadata'][0]['rssi'] ?? null,
            ], $payload)];
        }

        // Generic JSON
        $rows = isset($payload['readings']) && is_array($payload['readings']) ? $payload['readings'] : [$payload];

        return array_values(array_map(fn ($r) => $this->row($integration, (array) $r, [
            'device_id' => $r['device_id'] ?? $r['dev_eui'] ?? $r['imei'] ?? null,
            'meter_number' => $r['meter_number'] ?? $r['serial'] ?? null,
            'read_at' => $r['read_at'] ?? $r['timestamp'] ?? null,
            'frame_id' => isset($r['id']) ? (string) $r['id'] : null,
            'signal' => $r['signal'] ?? $r['rssi'] ?? null,
        ], (array) $r), array_filter($rows, 'is_array')));
    }

    protected function row(Integration $integration, array $decoded, array $meta, array $raw): array
    {
        $field = $integration->setting('value_field');
        $value = $field ? data_get($decoded, $field) : Arr::first(Arr::only($decoded, self::VALUE_FIELDS), fn ($v) => is_numeric($v));
        $divisor = (string) ($integration->setting('value_divisor') ?: '1');

        $alerts = collect((array) ($decoded['alerts'] ?? []))
            ->merge(collect(self::ALERTS)->filter(fn ($a) => ! empty($decoded[$a])))
            ->map(fn ($a) => strtolower((string) $a))
            ->unique()->values()->all();

        $battery = $decoded['battery'] ?? $decoded['battery_percent'] ?? $decoded['bat'] ?? null;

        try {
            $readAt = $meta['read_at'] ? CarbonImmutable::parse($meta['read_at']) : CarbonImmutable::now();
        } catch (Throwable) {
            $readAt = CarbonImmutable::now();
        }

        return [
            'device_id' => isset($meta['device_id']) ? strtolower((string) $meta['device_id']) : null,
            'meter_number' => isset($meta['meter_number']) ? (string) $meta['meter_number'] : null,
            'value' => is_numeric($value) && is_numeric($divisor) && (float) $divisor > 0
                ? (string) BigDecimal::of((string) $value)->dividedBy($divisor, 3, RoundingMode::HalfUp)
                : null,
            'read_at' => $readAt->setTimezone(config('app.timezone'))->startOfSecond(),
            'battery' => is_numeric($battery) ? max(0, min(100, (int) $battery)) : null,
            'signal' => is_numeric($meta['signal'] ?? null) ? (int) $meta['signal'] : null,
            'alerts' => $alerts,
            'frame_id' => $meta['frame_id'] ?? null,
            'raw' => $raw,
        ];
    }

    protected function findMeter(int $vendorId, ?string $deviceId, ?string $meterNumber): ?Meter
    {
        $query = Meter::withoutGlobalScope('vendor')->where('vendor_id', $vendorId);

        if ($deviceId) {
            return (clone $query)->whereRaw('LOWER(device_id) = ?', [strtolower($deviceId)])->first();
        }

        return $meterNumber ? $query->where('serial', $meterNumber)->first() : null;
    }

    /** Update the meter's live status and tell the team about new alerts. */
    protected function touch(Meter $meter, array $row, ?string $value): void
    {
        $previousAlerts = (array) ($meter->alerts ?? []);
        $isLatest = ! $meter->last_seen_at || $row['read_at']->greaterThanOrEqualTo($meter->last_seen_at);

        if (! $isLatest) {
            return;
        }

        $meter->forceFill(array_filter([
            'last_value' => $value,
            'last_seen_at' => $row['read_at'],
            'battery_percent' => $row['battery'],
        ], fn ($v) => $v !== null) + ['alerts' => $row['alerts'] ?: null])->save();

        $new = array_diff($row['alerts'], $previousAlerts);

        if ($new) {
            Notifier::send(
                Notifier::vendorTeam($meter->vendor_id, 'vendor.meters.manage'),
                'Smart meter alert',
                "Meter {$meter->serial}: ".implode(', ', array_map(fn ($a) => str_replace('_', ' ', $a), $new)).'.',
                route('vendor.meters.smart'),
                'exclamation-triangle',
                'meter_alert',
            );
        }
    }
}
