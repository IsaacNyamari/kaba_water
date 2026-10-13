<?php

namespace App\Models;

use App\Enums\ExpensePaymentMethod;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'expense_category_id', 'supplier_id', 'work_order_id',
        'reference', 'description', 'amount', 'tax', 'payment_method',
        'paid_at', 'receipt_path', 'recorded_by',
    ];

    protected $casts = [
        'paid_at'        => 'datetime',
        'amount'         => 'decimal:2',
        'tax'            => 'decimal:2',
        'payment_method' => ExpensePaymentMethod::class,
    ];

    public function category(): BelongsTo  { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function supplier(): BelongsTo  { return $this->belongsTo(Supplier::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function recordedBy(): BelongsTo{ return $this->belongsTo(User::class, 'recorded_by'); }

    public function totalWithTax(): float
    {
        return (float) $this->amount + (float) $this->tax;
    }
}