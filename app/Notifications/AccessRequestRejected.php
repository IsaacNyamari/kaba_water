<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccessRequestRejected extends Notification
{
    use Queueable;

    public function __construct(public AccessRequest $request) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your ' . config('app.name') . ' access request')
            ->greeting("Hello {$this->request->name},")
            ->line('Unfortunately we could not approve your access request.')
            ->when($this->request->rejection_reason, fn ($m) => $m->line('Reason: ' . $this->request->rejection_reason))
            ->line('If you think this is a mistake, reply to this email.');
    }
}
