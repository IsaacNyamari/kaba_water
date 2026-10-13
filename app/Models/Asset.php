<?php

namespace App\Models;

use App\Enums\AssetCategory;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'name', 'category', 'identifier',
        'purchase_date', 'purchase_cost', 'salvage_value',
        'depreciation_rate', 'status', 'disposal_date',
        'disposal_value', 'notes',
    ];

    protected $casts = [
        'purchase_date'     => 'date',
        'disposal_date'     => 'date',
        'purchase_cost'     => 'decimal:2',
        'salvage_value'     => 'decimal:2',
        'disposal_value'    => 'decimal:2',
        'depreciation_rate' => 'decimal:2',
        'category'          => AssetCategory::class,
    ];

    /**
     * Straight-line depreciation.
     * Returns the accumulated depreciation to date.
     */
    public function accumulatedDepreciation(): float
    {
        if ($this->status === 'disposed' && $this->disposal_date) {
            $asOf = $this->disposal_date;
        } else {
            $asOf = now();
        }

        $years = $this->purchase_date->floatDiffInYears($asOf);
        $cost  = (float) $this->purchase_cost;
        $salv  = (float) $this->salvage_value;
        $rate  = (float) $this->depreciation_rate / 100;

        // Straight-line cap: never depreciate below salvage value.
        $depreciableBase = max(0, $cost - $salv);
        $accumulated     = min($depreciableBase, $cost * $rate * $years);

        return round($accumulated, 2);
    }

    /** Net book value = cost − accumulated depreciation. */
    public function bookValue(): float
    {
        if ($this->status === 'disposed' && $this->disposal_value !== null) {
            return (float) $this->disposal_value;
        }

        return round((float) $this->purchase_cost - $this->accumulatedDepreciation(), 2);
    }
}