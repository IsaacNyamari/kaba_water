<?php

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Something happened on a work order that this person should know about:
 * a client raised a request, someone was assigned, or the status changed.
 *
 * Stored in the database (the notification bell) and broadcast over Reverb
 * so the bell updates live. Broadcasting goes through the queue, so a queue
 * worker and the Reverb server must be running for live updates; the stored
 * notification appears either way.
 */
class WorkOrderActivity extends Notification
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public string $title,
        public string $body,
        public string $url,
        public string $icon = 'wrench-screwdriver',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'work_order',
            'work_order_id' => $this->workOrder->id,
            'number' => $this->workOrder->number,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
