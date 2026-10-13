<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit trail row for closing / reopening a fiscal period. */
class FiscalPeriodEvent extends Model
{
    protected $fillable = ['fiscal_period_id', 'user_id', 'action', 'reason'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id')->withoutGlobalScope('vendor');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
