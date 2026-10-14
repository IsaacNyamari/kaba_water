<?php

namespace App\Actions\Marketing;

use App\Mail\ContactMessageAcknowledged;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;

/**
 * Store a public contact-form enquiry, then queue the sales notification and
 * the sender's auto-reply. The message is saved first so a mail or queue
 * outage never loses it, and the visitor never waits on SMTP.
 */
class SubmitContactMessage
{
    /**
     * @param  array{name: string, email: string, company?: string|null, message: string}  $data
     */
    public function handle(array $data): ContactMessage
    {
        $message = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => ($data['company'] ?? null) ?: null,
            'message' => $data['message'],
        ]);

        rescue(fn () => Mail::to(config('mail.sales'))->queue(new ContactMessageReceived($message)));
        rescue(fn () => Mail::to($message->email, $message->name)->queue(new ContactMessageAcknowledged($message)));

        return $message;
    }
}
