<?php

namespace App\Enums;

enum ReadingStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'Pending review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending  => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'red',
        };
    }
}