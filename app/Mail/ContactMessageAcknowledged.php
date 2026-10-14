<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Auto-reply to someone who used the public contact form.
 *
 * The sender's message is deliberately not quoted back: anyone can enter any
 * address in the form, so echoing free text would let it be used to send
 * arbitrary content to third parties.
 *
 * Sent via the queue so the visitor is not kept waiting on SMTP.
 */
class ContactMessageAcknowledged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Attempts before the job is marked failed (SMTP hiccups are usually brief). */
    public int $tries = 3;

    /** @var list<int> seconds to wait before each retry */
    public array $backoff = [60, 300];

    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your message — '.config('app.name'),
            replyTo: [new Address(config('mail.sales'), config('app.name'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-acknowledgement',
            with: [
                'firstName' => Str::limit(Str::before(trim($this->contactMessage->name), ' '), 40, ''),
            ],
        );
    }
}
