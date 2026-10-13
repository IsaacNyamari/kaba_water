<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Pending = 'pending';   // waiting for an admin (or the auto-check) to decide
    case Approved = 'approved';  // signup link sent, not used yet
    case Rejected = 'rejected';
    case Accepted = 'accepted';  // the person signed up through the link

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Invited',
            self::Rejected => 'Rejected',
            self::Accepted => 'Signed up',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Accepted => 'emerald',
        };
    }
}
