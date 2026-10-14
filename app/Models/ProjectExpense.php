<?php

namespace App\Models;

use App\Support\Money\MoneyMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project cost claim: submitted → approved / rejected → paid (partly or fully).
 *
 * Approved, non-settlement expenses are recognised project cost. A settlement
 * (is_settlement) pays for materials, labour or equipment already costed
 * elsewhere in the project, so it counts toward cash paid but never as cost.
 */
class ProjectExpense extends Model
{
    public const STATUSES = ['submitted' => 'Awaiting approval', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public const PAYMENT_METHODS = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank transfer', 'cheque' => 'Cheque', 'credit' => 'On credit'];

    protected $fillable = [
        'project_id', 'category', 'description', 'justification', 'expense_date', 'amount', 'currency',
        'supplier_id', 'payee_name', 'payment_method', 'reference', 'stage', 'is_settlement', 'status',
        'paid_amount', 'receipt_path', 'submitted_by', 'approved_by', 'approved_at', 'rejection_reason', 'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'is_settlement' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ProjectSupplier::class, 'supplier_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function categoryLabel(): string
    {
        return config("projects.expense_categories.{$this->category}", ucfirst($this->category));
    }

    public function payee(): string
    {
        return $this->supplier?->name ?? $this->payee_name ?? '—';
    }

    /** unpaid | partial | paid */
    public function paymentStatus(): string
    {
        return match (true) {
            ! MoneyMath::isPositive((string) $this->paid_amount) => 'unpaid',
            MoneyMath::compare((string) $this->paid_amount, (string) $this->amount) >= 0 => 'paid',
            default => 'partial',
        };
    }

    public function outstanding(): string
    {
        return MoneyMath::sub((string) $this->amount, (string) $this->paid_amount);
    }

    public function isOverApprovalLimit(): bool
    {
        return MoneyMath::compare((string) $this->amount, (string) config('projects.expense_approval_limit')) > 0;
    }
}
