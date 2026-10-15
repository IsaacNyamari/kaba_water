<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use App\Support\Money\MoneyMath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One M-Pesa payment into the vendor's Paybill / Till, either pushed by
 * Safaricom (C2B confirmation) or started by us (STK push). Holds the raw
 * callback and how much of it has been applied to invoices or tokens.
 */
class MpesaTransaction extends Model
{
    use BelongsToVendor;

    public const STATUSES = [
        'pending' => 'Awaiting PIN',
        'failed' => 'Failed',
        'matched' => 'Reconciled',
        'credit' => 'Customer credit',
        'suspense' => 'Suspense',
    ];

    protected $fillable = [
        'vendor_id', 'channel', 'purpose', 'receipt', 'checkout_request_id', 'merchant_request_id',
        'phone', 'payer_name', 'amount', 'allocated_amount', 'account_reference', 'short_code',
        'status', 'client_id', 'invoice_id', 'meter_id', 'result_code', 'result_description',
        'payload', 'transacted_at', 'reconciled_by', 'reconciled_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'payload' => 'array',
        'transacted_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function token(): HasOne
    {
        return $this->hasOne(PrepaidToken::class);
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /** Money received but not yet applied to an invoice or a token. */
    public function unallocated(): string
    {
        return MoneyMath::sub((string) $this->amount, (string) $this->allocated_amount);
    }

    public function hasUnallocated(): bool
    {
        return MoneyMath::isPositive($this->unallocated());
    }

    public function scopeNeedsAttention(Builder $q): Builder
    {
        return $q->whereIn('status', ['suspense', 'credit']);
    }

    public function statusColor(): string
    {
        return [
            'pending' => 'zinc', 'failed' => 'red', 'matched' => 'emerald', 'credit' => 'sky', 'suspense' => 'amber',
        ][$this->status] ?? 'zinc';
    }
}
