<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A billable house, apartment, bedsitter or shop, known by its house number
 * and optionally grouped under a property. Its tenant (a client) is billed
 * the monthly rent and service charge; meters in the house bill water
 * through readings as usual.
 */
class Unit extends Model
{
    use BelongsToVendor;

    public const TYPES = [
        'apartment' => 'Apartment',
        'house' => 'House',
        'bedsitter' => 'Bedsitter / single room',
        'shop' => 'Shop',
        'office' => 'Office',
        'other' => 'Other',
    ];

    protected $fillable = [
        'vendor_id', 'property_id', 'client_id', 'house_number', 'type',
        'rent', 'service_charge', 'deposit', 'notes',
    ];

    protected $casts = [
        'rent' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'deposit' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** The current tenant. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeOccupied(Builder $query): void
    {
        $query->whereNotNull('client_id');
    }

    public function isOccupied(): bool
    {
        return $this->client_id !== null;
    }

    /** "Sunrise Apartments · A4", or just "A4" when the house is not under a property. */
    public function fullLabel(): string
    {
        return ($this->property?->name ? $this->property->name.' · ' : '').$this->house_number;
    }

    /** Rent + service charge billed every month (as a 2-dp string). */
    public function monthlyCharge(): string
    {
        return number_format((float) $this->rent + (float) $this->service_charge, 2, '.', '');
    }

    /** Whether this unit already has a live (non-void) rent invoice for the YYYY-MM period. */
    public function isBilledFor(string $period): bool
    {
        return $this->invoices()
            ->where('billing_period', $period)
            ->where('status', '!=', InvoiceStatus::Void)
            ->exists();
    }
}
