<?php

namespace App\Enums;

enum WorkOrderPriority: string
{
    case Low    = 'low';
    case Normal = 'normal';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string { return ucfirst($this->value); }

    public function color(): string
    {
        return match($this) {
            self::Low    => 'zinc',
            self::Normal => 'blue',
            self::High   => 'amber',
            self::Urgent => 'red',
        };
    }
}