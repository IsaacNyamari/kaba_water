<?php

namespace App\Models;

use App\Enums\ReadingStatus;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reading extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id',
        'meter_id',
        'reading',
        'previous_reading',
        'consumption',
        'source',
        'status',
        'rejection_reason',
        'photo_path',
        'read_at',
        'created_by',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'status'  => ReadingStatus::class,
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', ReadingStatus::Approved->value);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', ReadingStatus::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->status === ReadingStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ReadingStatus::Approved;
    }

    // ── Boot hooks ─────────────────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (Reading $r) {
            // Clients submit "pending"; everyone else auto-approves.
            if (empty($r->status)) {
                $user = auth()->user();
                $r->status = ($user && $user->hasRole('client'))
                    ? \App\Enums\ReadingStatus::Pending->value
                    : \App\Enums\ReadingStatus::Approved->value;
            }

            // Compute previous + consumption vs. last APPROVED reading.
            if ($r->previous_reading === null) {
                $prev = static::withoutGlobalScope('vendor')
                    ->where('meter_id', $r->meter_id)
                    ->where('status', \App\Enums\ReadingStatus::Approved->value)
                    ->latest('read_at')
                    ->value('reading');

                $r->previous_reading = $prev ?? 0;
                $r->consumption      = max(0, (int) $r->reading - (int) $r->previous_reading);
            }
        });
    }
}
