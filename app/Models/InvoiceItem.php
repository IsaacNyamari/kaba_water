<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /** Line categories; each maps to a revenue account in config accounting.invoice_item_accounts. */
    public const WATER = 'water';

    public const RENT = 'rent';

    public const SERVICE_CHARGE = 'service_charge';

    public const OTHER = 'other';

    protected $fillable = ['invoice_id', 'description', 'category', 'quantity', 'unit_price', 'total'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
