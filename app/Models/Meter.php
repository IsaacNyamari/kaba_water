<?php

namespace App\Models;

use App\Enums\MeterStatus;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;

class Meter extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'client_id', 'unit_id', 'serial',
        'location', 'installed_at', 'status',
        'billing_mode', 'device_id', 'connectivity', 'last_value', 'last_seen_at', 'battery_percent', 'alerts',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'status' => MeterStatus::class,
        'last_value' => 'decimal:3',
        'last_seen_at' => 'datetime',
        'alerts' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** The house the meter is fitted in (property owners). */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class)->latest('read_at');
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(MeterTelemetry::class)->latest('read_at');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(PrepaidToken::class)->latest();
    }

    public function isPrepaid(): bool
    {
        return $this->billing_mode === 'prepaid';
    }

    public function isSmart(): bool
    {
        return filled($this->device_id);
    }

    /** Signed public link where the customer can buy a token for this prepaid meter. */
    public function tokenUrl(): string
    {
        return URL::signedRoute('pay.token', $this);
    }

    /** The buy-token page as a short link (/p/abc1234) that fits in an SMS. */
    public function shortTokenUrl(): string
    {
        return ShortLink::urlFor('token', $this->id, $this->vendor_id);
    }

    /** A smart meter that has not reported for over a day is offline. */
    public function isOnline(): bool
    {
        return (bool) $this->last_seen_at?->gt(now()->subDay());
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(Reading::class)->latestOfMany('read_at');
    }
}
