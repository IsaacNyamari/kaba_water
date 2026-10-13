<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalPeriod extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id', 'name', 'start_date', 'end_date',
        'is_closed', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_closed' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FiscalPeriodEvent::class)->latest();
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('is_closed', false);
    }

    /** Periods that contain $date. */
    public function scopeForDate(Builder $q, $date): Builder
    {
        $d = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;

        return $q->whereDate('start_date', '<=', $d)->whereDate('end_date', '>=', $d);
    }
}
