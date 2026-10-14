<?php

namespace App\Observers;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Notifier;

/**
 * Work order notifications (database + live over Reverb):
 *  - a client raises a request        → the vendor's work-order managers
 *  - someone is assigned              → the assignee, and the client
 *  - the status changes               → the client; and the managers too
 *                                       when the assignee made the change
 */
class WorkOrderObserver
{
    public function created(WorkOrder $workOrder): void
    {
        if (auth()->user()?->hasRole('client')) {
            Notifier::send(
                Notifier::vendorTeam($workOrder->vendor_id, 'vendor.work_orders.manage'),
                'New request from '.($workOrder->client?->name ?? 'a client'),
                $workOrder->title.' · '.$workOrder->type->label().' · '.$workOrder->priority->label().' priority',
                route('vendor.work-orders.edit', $workOrder),
                $workOrder->type->icon(),
                'work_order',
            );
        }

        if ($workOrder->assigned_to) {
            $this->notifyAssignee($workOrder);
        }
    }

    public function updated(WorkOrder $workOrder): void
    {
        if ($workOrder->wasChanged('assigned_to') && $workOrder->assigned_to) {
            $this->notifyAssignee($workOrder);

            Notifier::send(
                Notifier::clientUser($workOrder->client),
                'Someone is on it: '.$workOrder->number,
                ($workOrder->assignee?->name ?? 'A technician').' has been assigned to "'.$workOrder->title.'".',
                route('portal.work-orders.index'),
                'user-plus',
                'work_order',
            );
        }

        if ($workOrder->wasChanged('status')) {
            $title = $workOrder->number.' is now '.strtolower($workOrder->status->label());
            $icon = match ($workOrder->status) {
                WorkOrderStatus::Completed => 'check-circle',
                WorkOrderStatus::Cancelled => 'x-circle',
                WorkOrderStatus::InProgress => 'arrow-path',
                default => 'clipboard-document',
            };

            Notifier::send(Notifier::clientUser($workOrder->client), $title, $this->statusMessage($workOrder), route('portal.work-orders.index'), $icon, 'work_order');

            // The technician updated their own job: keep the office informed.
            if ($workOrder->assigned_to && auth()->id() === $workOrder->assigned_to) {
                Notifier::send(
                    Notifier::vendorTeam($workOrder->vendor_id, 'vendor.work_orders.manage'),
                    $title,
                    auth()->user()->name.': '.$this->statusMessage($workOrder),
                    route('vendor.work-orders.edit', $workOrder),
                    $icon,
                    'work_order',
                );
            }
        }
    }

    protected function notifyAssignee(WorkOrder $workOrder): void
    {
        Notifier::send(
            User::find($workOrder->assigned_to),
            'New job for you: '.$workOrder->number,
            $workOrder->title.($workOrder->client ? ' · '.$workOrder->client->name : '').' · '.$workOrder->priority->label().' priority',
            route('vendor.jobs.index'),
            'clipboard-document-check',
            'work_order',
        );
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
}
