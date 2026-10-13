<?php

namespace App\Models\Concerns;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToVendor
{
    protected static function bootBelongsToVendor(): void
    {
        // Auto-scope every query to the current vendor
        static::addGlobalScope('vendor', function (Builder $q) {
            if ($id = static::currentVendorId()) {
                $q->where($q->getModel()->getTable() . '.vendor_id', $id);
            }
        });

        // Auto-fill vendor_id on create
        static::creating(function ($model) {
            if (empty($model->vendor_id)) {
                $model->vendor_id = static::currentVendorId();
            }
        });
    }

    /**
     * Resolve the active vendor id from either:
     *   1. app('current_vendor_id')  — set by EnsureVendorContext on vendor routes
     *   2. auth()->user()->vendor_id — fallback for Livewire AJAX requests
     */
    protected static function currentVendorId(): ?int
    {
        if (app()->bound('current_vendor_id')) {
            return app('current_vendor_id');
        }

        return auth()->user()?->vendor_id;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}