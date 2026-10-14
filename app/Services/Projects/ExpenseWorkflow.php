<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectExpense;
use App\Support\Money\MoneyMath;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project expenses: submit → approve / reject → record part or full payments.
 * Every step is logged on the project; duplicates are refused.
 */
class ExpenseWorkflow
{
    /** @param array<string, mixed> $data validated form data */
    public function submit(Project $project, array $data): ProjectExpense
    {
        $duplicate = $project->expenses()
            ->where('status', '!=', 'rejected')
            ->whereDate('expense_date', $data['expense_date'])
            ->where('amount', MoneyMath::str((string) $data['amount']))
            ->where(fn ($q) => filled($data['reference'] ?? null)
                ? $q->where('reference', $data['reference'])
                : $q->where('description', $data['description'])->where('supplier_id', $data['supplier_id'] ?? null))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['amount' => 'This looks like a duplicate — the same amount, date and payee/reference is already on this project.']);
        }

        $expense = DB::transaction(function () use ($project, $data) {
            $expense = $project->expenses()->create($data + [
                'status' => 'submitted',
                'paid_amount' => '0',
                'stage' => $project->status,
                'submitted_by' => auth()->id(),
                'currency' => MoneyMath::currency(),
            ]);

            $project->log('expense_submitted', "Expense submitted: {$expense->description} (".MoneyMath::format((string) $expense->amount).')', [
                'expense_id' => $expense->id, 'amount' => (string) $expense->amount, 'over_limit' => $expense->isOverApprovalLimit(),
            ]);

            return $expense;
        });

        $limit = $expense->isOverApprovalLimit() ? ' — above the approval limit' : '';
        Notifier::send(
            ProjectAlerts::stakeholders($project, 'projects.expenses.approve'),
            "Expense to approve{$limit}",
            "{$project->reference} · {$expense->description} · ".MoneyMath::format((string) $expense->amount),
            route('projects.show', ['project' => $project, 'tab' => 'expenses']),
            'receipt-percent',
            'project_expense',
        );

        return $expense;
    }

    public function approve(ProjectExpense $expense): void
    {
        $this->decide($expense, 'approved');
    }

    public function reject(ProjectExpense $expense, string $reason): void
    {
        $this->decide($expense, 'rejected', $reason);
    }

    protected function decide(ProjectExpense $expense, string $status, ?string $reason = null): void
    {
        if ($expense->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'This expense has already been '.$expense->status.'.']);
        }

        DB::transaction(function () use ($expense, $status, $reason) {
            $expense->update([
                'status' => $status,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $expense->project->log("expense_{$status}", ucfirst($status).": {$expense->description} (".MoneyMath::format((string) $expense->amount).')'.($reason ? " — {$reason}" : ''), [
                'expense_id' => $expense->id, 'amount' => (string) $expense->amount,
            ]);
        });

        ProjectAlerts::notifyUser($expense->submitter, "Expense {$status}", "{$expense->description} · ".MoneyMath::format((string) $expense->amount).($reason ? " — {$reason}" : ''), $expense->project, 'expenses', $status === 'approved' ? 'check-circle' : 'x-circle');

        if ($status === 'approved') {
            app(ProjectAlerts::class)->checkBudget($expense->project);
        }
    }

    public function recordPayment(ProjectExpense $expense, string $amount, ?string $method = null): void
    {
        if ($expense->status !== 'approved') {
            throw ValidationException::withMessages(['payment' => 'Only approved expenses can be paid.']);
        }

        DB::transaction(function () use ($expense, $amount, $method) {
            $expense = ProjectExpense::query()->lockForUpdate()->findOrFail($expense->id);

            if (! MoneyMath::isPositive($amount) || MoneyMath::compare($amount, $expense->outstanding()) > 0) {
                throw ValidationException::withMessages(['payment' => 'Enter an amount between 0 and the '.MoneyMath::format($expense->outstanding()).' still owed.']);
            }

            $expense->update([
                'paid_amount' => MoneyMath::add((string) $expense->paid_amount, $amount),
                'payment_method' => $method ?: $expense->payment_method,
            ]);

            $expense->project->log('expense_paid', "Paid {$expense->payee()} ".MoneyMath::format($amount)." for {$expense->description}", [
                'expense_id' => $expense->id, 'amount' => $amount, 'paid_total' => (string) $expense->paid_amount,
            ]);
        });
    }
}
