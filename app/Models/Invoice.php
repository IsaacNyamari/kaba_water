<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToVendor;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'client_id', 'reading_id', 'number',
        'issued_at', 'due_at', 'subtotal', 'tax', 'total',
        'amount_paid', 'status', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'issued_at'   => 'datetime',
        'due_at'      => 'datetime',
        'status'      => InvoiceStatus::class,
        'subtotal'    => 'decimal:2',
        'tax'         => 'decimal:2',
        'total'       => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function client(): BelongsTo  { return $this->belongsTo(Client::class); }
    public function reading(): BelongsTo { return $this->belongsTo(Reading::class); }
    public function items(): HasMany     { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany  { return $this->hasMany(Payment::class); }

    public function balance(): float
    {
        return max(0, (float) $this->total - (float) $this->amount_paid);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true)
            && (bool) $this->due_at?->isPast();
    }

    /**
     * Recompute amount_paid from the payments table and derive the status:
     * paid when fully settled, otherwise sent/overdue based on the due date.
     * Draft and void invoices keep their status.
     */
    public function syncPayments(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);

        $status = match (true) {
            $this->status === InvoiceStatus::Void              => InvoiceStatus::Void,
            $paid + 0.001 >= (float) $this->total && $paid > 0 => InvoiceStatus::Paid,
            $this->status === InvoiceStatus::Draft             => InvoiceStatus::Draft,
            (bool) $this->due_at?->isPast()                    => InvoiceStatus::Overdue,
            default                                            => InvoiceStatus::Sent,
        };

        $this->update(['amount_paid' => $paid, 'status' => $status]);
    }
}