<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = ['vendor_id', 'plan_id', 'status', 'starts_at', 'ends_at'];
    protected $casts    = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function plan(): BelongsTo   { return $this->belongsTo(Plan::class); }
}