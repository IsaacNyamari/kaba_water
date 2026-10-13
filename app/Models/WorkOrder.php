<?php

namespace App\Models;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'client_id', 'meter_id', 'assigned_to',
        'number', 'type', 'priority', 'status', 'title',
        'description', 'resolution',
        'scheduled_at', 'started_at', 'completed_at',
        'materials_cost', 'labor_cost',
        'billable', 'invoice_id',
    ];

    protected $casts = [
        'type'           => WorkOrderType::class,
        'status'         => WorkOrderStatus::class,
        'priority'       => WorkOrderPriority::class,
        'scheduled_at'   => 'datetime',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'materials_cost' => 'decimal:2',
        'labor_cost'     => 'decimal:2',
        'billable'       => 'boolean',
    ];

    public function client(): BelongsTo    { return $this->belongsTo(Client::class); }
    public function meter(): BelongsTo     { return $this->belongsTo(Meter::class); }
    public function assignee(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_to'); }
    public function invoice(): BelongsTo   { return $this->belongsTo(Invoice::class); }
    public function expenses(): HasMany    { return $this->hasMany(Expense::class); }

    /** Next per-vendor number, WO-YYYY-NNNN (based on the highest existing, not a count). */
    public static function nextNumber(int $vendorId): string
    {
        $prefix = 'WO-' . now()->year . '-';

        $last = static::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function totalCost(): float
    {
        return (float) $this->materials_cost + (float) $this->labor_cost;
    }

    // ── Scopes ──────────────────────────────────────────────
    public function scopeOpen($q)       { return $q->whereIn('status', ['pending', 'assigned', 'in_progress']); }
    public function scopeCompleted($q)  { return $q->where('status', 'completed'); }
}