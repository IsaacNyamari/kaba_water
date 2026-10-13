<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use App\Support\Money\MoneyMath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A double-entry journal entry. Once posted_at is set it is immutable:
 * the only allowed change is linking the entry that reverses it.
 *
 * @property int $id
 * @property int $vendor_id
 * @property Carbon $date
 * @property Carbon|null $posted_at
 */
class JournalEntry extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id', 'date', 'reference', 'memo',
        'source_type', 'source_id', 'source_event',
        'created_by', 'posted_at', 'reversed_by_id', 'reverses_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalEntry $entry) {
            if ($entry->getOriginal('posted_at') === null) {
                return; // drafts (or the moment of posting) may change
            }

            $changed = array_keys($entry->getDirty());
            $allowed = ['reversed_by_id', 'updated_at'];

            if (array_diff($changed, $allowed) !== []) {
                throw new LogicException("Journal entry #{$entry->id} is posted and cannot be edited — reverse it instead.");
            }
        });

        static::deleting(function (JournalEntry $entry) {
            if ($entry->getOriginal('posted_at') !== null) {
                throw new LogicException("Journal entry #{$entry->id} is posted and cannot be deleted — reverse it instead.");
            }
        });
    }

    // ── Relations ──────────────────────────────────────────
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** The entry this one reverses. */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /** The entry that reversed this one. */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopePosted(Builder $q): Builder
    {
        return $q->whereNotNull('posted_at');
    }

    public function scopeForPeriod(Builder $q, $from, $to): Builder
    {
        return $q->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
    }

    public function scopeForVendor(Builder $q, int $vendorId): Builder
    {
        return $q->withoutGlobalScope('vendor')->where($this->getTable().'.vendor_id', $vendorId);
    }

    // ── Accessors ──────────────────────────────────────────
    /** Sum of debits as a 2-dp decimal string (no floats). */
    protected function totalDebit(): Attribute
    {
        return Attribute::get(fn () => MoneyMath::sumColumn($this->lines, 'debit'));
    }

    protected function totalCredit(): Attribute
    {
        return Attribute::get(fn () => MoneyMath::sumColumn($this->lines, 'credit'));
    }

    protected function isBalanced(): Attribute
    {
        return Attribute::get(fn () => MoneyMath::equals($this->total_debit, $this->total_credit));
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }
}
