<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id', 'account_id', 'client_id', 'meter_id',
        'debit', 'credit', 'description',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Lines of a posted entry are as immutable as the entry itself.
        $guard = function (JournalLine $line) {
            $postedAt = JournalEntry::withoutGlobalScope('vendor')
                ->whereKey($line->journal_entry_id)
                ->value('posted_at');

            if ($postedAt !== null) {
                throw new LogicException('Lines of a posted journal entry cannot be changed.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id')->withoutGlobalScope('vendor');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id')->withoutGlobalScope('vendor')->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withoutGlobalScope('vendor');
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class)->withoutGlobalScope('vendor');
    }
}
