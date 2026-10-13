<?php

namespace App\Models;

use App\Enums\LiabilityCategory;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Liability extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'supplier_id', 'name', 'category',
        'principal', 'amount_paid', 'interest_rate',
        'started_at', 'due_at', 'status', 'notes',
    ];

    protected $casts = [
        'started_at'    => 'date',
        'due_at'        => 'date',
        'principal'     => 'decimal:2',
        'amount_paid'   => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'category'      => LiabilityCategory::class,
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }

    public function outstanding(): float
    {
        return max(0, (float) $this->principal - (float) $this->amount_paid);
    }
}