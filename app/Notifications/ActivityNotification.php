<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * One in-app notification for anything that happens in the system: a work
 * order request, a reading to review, a new bill, a payment, an access
 * request, a team change…
 *
 * Stored in the database (the notification bell) and broadcast over Reverb
 * so the bell — and any page listening — updates live. It carries only plain
 * values (no models), so the queued broadcast never depends on a record
 * still existing. Send it through App\Support\Notifier.
 */
class ActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $icon = 'bell',
        public string $kind = 'general',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{kind: string, title: string, body: string, url: string, icon: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
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
