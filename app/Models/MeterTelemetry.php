<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One reading pushed by a smart meter (LoRaWAN / NB-IoT / GPRS). Billing readings are taken from these. */
class MeterTelemetry extends Model
{
    use BelongsToVendor;

    protected $table = 'meter_telemetry';

    protected $fillable = [
        'vendor_id', 'meter_id', 'value', 'read_at', 'battery_percent', 'signal', 'alerts', 'frame_id', 'payload',
    ];

    protected $casts = [
        'value' => 'decimal:3',
        'read_at' => 'datetime',
        'alerts' => 'array',
        'payload' => 'array',
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
