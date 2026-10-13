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
        $where = $r->vendor?->name ? " for {$r->vendor->name}" : '';

        return (new MailMessage)
            ->subject('Your '.config('app.name').' access has been approved')
            ->greeting("Hello {$r->name},")
            ->line("Your request for access{$where} has been approved.")
            ->line('Use the button below to set your password and finish creating your account.')
            ->action('Create my account', $this->url)
            ->line('This link works once and expires on '.$r->invite_expires_at?->format('d M Y H:i').'.')
            ->line('If you did not ask for access, you can ignore this email.');
    }
}
