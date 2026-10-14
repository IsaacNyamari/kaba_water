<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sends the approved requester their single-use signup link. */
class AccessInvitation extends Notification
{
    use Queueable;

    public function __construct(public AccessRequest $request, public string $url) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;

        return (new MailMessage)
            ->subject('Your '.config('app.name').' access has been approved')
            ->markdown('mail.access.invitation', [
                'accessRequest' => $r,
                'workspace' => $r->vendor?->name,
                'url' => $this->url,
            ]);
    }
}
