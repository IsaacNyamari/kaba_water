<?php

namespace App\Enums;

/**
 * Outcome of the automatic email check on an access request.
 *
 * Note: no check can prove a specific mailbox exists without sending to it
 * (SMTP probing is blocked or faked by most providers). "Deliverable" means the
 * address is well-formed, its domain accepts mail (MX/A records) and it is not a
 * throw-away provider. The emailed signup link is the final proof of ownership.
 */
enum EmailCheckResult: string
{
    case Deliverable = 'deliverable';
    case Disposable = 'disposable';
    case NoMailServer = 'no_mail_server';
    case Invalid = 'invalid';

    public function passed(): bool
    {
        return $this === self::Deliverable;
    }

    public function label(): string
    {
        return match ($this) {
            self::Deliverable => 'Email looks real',
            self::Disposable => 'Disposable email',
            self::NoMailServer => 'Domain has no mail server',
            self::Invalid => 'Invalid address',
        };
    }

    public function color(): string
    {
        return $this->passed() ? 'emerald' : 'red';
    }
}
