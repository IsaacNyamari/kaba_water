<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Client extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id',
        'user_id',
        'account_no',
        'name',
        'email',
        'phone',
        'address',
        'house_number',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    /** The houses this client rents. */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function balance(): float
    {
        // Outstanding = what is still owed after partial payments.
        return (float) $this->invoices()
            ->whereIn('status', ['sent', 'overdue'])
            ->sum(DB::raw('total - amount_paid'));
    }

    public static function nextAccountNo(int $vendorId): string
    {
        $last = static::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->orderByDesc('id')
            ->value('account_no');

        $next = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;

        return 'C'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
