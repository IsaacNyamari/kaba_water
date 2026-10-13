<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells platform admins a new access request is waiting. */
class AccessRequestSubmitted extends Notification
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
        $r = $this->request;

        return (new MailMessage)
            ->subject("Access request from {$r->name}")
            ->greeting('New access request')
            ->line("{$r->name} <{$r->email}> asked for a ".($r->requested_type === AccessRequest::TYPE_VENDOR ? 'vendor workspace' : 'client portal account').'.')
            ->when($r->organization, fn ($m) => $m->line("Organisation: {$r->organization}"))
            ->line('Email check: '.($r->email_check?->label() ?? 'not run'))
            ->action('Review requests', route('admin.access-requests.index'));
    }
}
