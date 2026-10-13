<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'invoice_id', 'client_id',
        'amount', 'method', 'reference', 'receipt_path',
        'paid_at', 'recorded_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'method'  => PaymentMethod::class,
        'amount'  => 'decimal:2',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function client(): BelongsTo  { return $this->belongsTo(Client::class); }
}