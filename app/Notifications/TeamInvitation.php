<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Welcomes a new team member and links them to set their own password. */
class TeamInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public string $workspace,
        public string $roleLabel,
        public string $inviterName,
        public string $setPasswordUrl,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been added to {$this->workspace}")
            ->markdown('mail.team-invitation', [
                'name' => $notifiable->name,
                'workspace' => $this->workspace,
                'roleLabel' => $this->roleLabel,
                'inviterName' => $this->inviterName,
                'url' => $this->setPasswordUrl,
            ]);
    }
}
