<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Pending    = 'pending';
    case Assigned   = 'assigned';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Pending',
            self::Assigned   => 'Assigned',
            self::InProgress => 'In Progress',
            self::Completed  => 'Completed',
            self::Cancelled  => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending    => 'amber',
            self::Assigned   => 'blue',
            self::InProgress => 'violet',
            self::Completed  => 'emerald',
            self::Cancelled  => 'zinc',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Assigned, self::InProgress], true);
    }
}
