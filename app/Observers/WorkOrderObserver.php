<?php

namespace App\Observers;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the right people when a work order changes:
 *  - a client raises a request        → the vendor's work-order managers
 *  - someone is assigned              → the assignee, and the client
 *  - the status changes               → the client
 * The person who made the change is never notified about their own action.
 * Failures to notify never block saving the work order.
 */
class WorkOrderObserver
{
    public function created(WorkOrder $workOrder): void
    {
        $actor = auth()->user();

        if ($actor?->hasRole('client')) {
            $this->send($this->vendorManagers($workOrder), new WorkOrderActivity(
                $workOrder,
                title: 'New request from '.($workOrder->client?->name ?? 'a client'),
                body: $workOrder->title.' · '.$workOrder->type->label().' · '.$workOrder->priority->label().' priority',
                url: route('vendor.work-orders.edit', $workOrder),
                icon: $workOrder->type->icon(),
            ));
        }

        if ($workOrder->assigned_to) {
            $this->notifyAssignee($workOrder);
        }
    }

    public function updated(WorkOrder $workOrder): void
    {
        if ($workOrder->wasChanged('assigned_to') && $workOrder->assigned_to) {
            $this->notifyAssignee($workOrder);

            $this->send($this->clientUser($workOrder), new WorkOrderActivity(
                $workOrder,
                title: 'Someone is on it: '.$workOrder->number,
                body: ($workOrder->assignee?->name ?? 'A technician').' has been assigned to "'.$workOrder->title.'".',
                url: route('portal.work-orders.index'),
                icon: 'user-plus',
            ));
        }

        if ($workOrder->wasChanged('status')) {
            $this->send($this->clientUser($workOrder), new WorkOrderActivity(
                $workOrder,
                title: $workOrder->number.' is now '.strtolower($workOrder->status->label()),
                body: $this->statusMessage($workOrder),
                url: route('portal.work-orders.index'),
                icon: match ($workOrder->status) {
                    WorkOrderStatus::Completed => 'check-circle',
                    WorkOrderStatus::Cancelled => 'x-circle',
                    WorkOrderStatus::InProgress => 'arrow-path',
                    default => 'clipboard-document',
                },
            ));
        }
    }

    protected function notifyAssignee(WorkOrder $workOrder): void
    {
        $this->send(User::whereKey($workOrder->assigned_to)->get(), new WorkOrderActivity(
            $workOrder,
            title: 'Assigned to you: '.$workOrder->number,
            body: $workOrder->title.($workOrder->client ? ' · '.$workOrder->client->name : ''),
            url: route('vendor.work-orders.edit', $workOrder),
            icon: 'clipboard-document-check',
        ));
    }

    protected function statusMessage(WorkOrder $workOrder): string
    {
        return match ($workOrder->status) {
            WorkOrderStatus::InProgress => 'Work has started on "'.$workOrder->title.'".',
            WorkOrderStatus::Completed => '"'.$workOrder->title.'" is done.'.($workOrder->resolution ? ' '.$workOrder->resolution : ''),
            WorkOrderStatus::Cancelled => '"'.$workOrder->title.'" was cancelled.',
            default => '"'.$workOrder->title.'" was updated.',
        };
    }

    /** Vendor users who can manage work orders (owner and anyone granted the permission). */
    protected function vendorManagers(WorkOrder $workOrder): Collection
    {
        return rescue(fn () => User::query()
            ->where('vendor_id', $workOrder->vendor_id)
            ->permission('vendor.work_orders.manage')
            ->get(), collect());
    }

    protected function clientUser(WorkOrder $workOrder): Collection
    {
        $userId = $workOrder->client?->user_id;

        return $userId ? User::whereKey($userId)->get() : collect();
    }

    /** Send to everyone except the person who made the change. */
    protected function send(Collection $users, WorkOrderActivity $notification): void
    {
        $recipients = $users->reject(fn (User $user) => $user->id === auth()->id());

        if ($recipients->isNotEmpty()) {
            rescue(fn () => Notification::send($recipients, $notification));
        }
    }
}
