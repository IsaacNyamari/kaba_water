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

    /**
     * The most recent APPROVED reading value for a meter, optionally only
     * readings taken at or before $before and excluding one reading id.
     */
    public static function lastApprovedValueFor(int $meterId, $before = null, ?int $exceptId = null): ?int
    {
        $value = static::withoutGlobalScope('vendor')
            ->where('meter_id', $meterId)
            ->where('status', ReadingStatus::Approved->value)
            ->when($before, fn ($q) => $q->where('read_at', '<=', $before))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderByDesc('read_at')
            ->orderByDesc('id')
            ->value('reading');

        return $value === null ? null : (int) $value;
    }

    /**
     * Approve a pending reading and recompute previous/consumption against
     * the latest approved reading at that moment (other readings may have
     * been approved since this one was submitted).
     */
    public function approve(): void
    {
        $previous = static::lastApprovedValueFor($this->meter_id, $this->read_at, $this->id)
            ?? (int) ($this->previous_reading ?? 0);

        $this->update([
            'status'           => ReadingStatus::Approved,
            'rejection_reason' => null,
            'previous_reading' => $previous,
            'consumption'      => max(0, (int) $this->reading - $previous),
        ]);
    }

    public function reject(?string $reason = null): void
    {
        $this->update([
            'status'           => ReadingStatus::Rejected,
            'rejection_reason' => $reason ?: null,
        ]);
    }

    // ── Boot hooks ─────────────────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (Reading $r) {
            // Clients submit "pending"; everyone else auto-approves.
            if (empty($r->status)) {
                $user = auth()->user();
                $r->status = ($user && $user->hasRole('client'))
                    ? ReadingStatus::Pending->value
                    : ReadingStatus::Approved->value;
            }

            // Previous = last APPROVED reading taken at or before this one,
            // unless the caller seeded it (e.g. the first reading of a new meter).
            if ($r->previous_reading === null) {
                $r->previous_reading = static::lastApprovedValueFor($r->meter_id, $r->read_at) ?? 0;
            }

            // Always derive consumption from the two readings.
            if ($r->consumption === null) {
                $r->consumption = max(0, (int) $r->reading - (int) $r->previous_reading);
            }
        });
    }
}
