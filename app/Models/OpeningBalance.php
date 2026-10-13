<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalance extends Model
{
    use BelongsToVendor;

    protected $fillable = ['vendor_id', 'as_of_date', 'is_posted', 'journal_entry_id', 'notes'];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'is_posted' => 'boolean',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class)->withoutGlobalScope('vendor');
    }
}
