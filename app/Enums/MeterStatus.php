<?php

namespace App\Enums;

enum MeterStatus: string
{
    case Active  = 'active';
    case Faulty  = 'faulty';
    case Removed = 'removed';

    public function label(): string { return ucfirst($this->value); }

    public function color(): string
    {
        return match($this) {
            self::Active  => 'emerald',
            self::Faulty  => 'amber',
            self::Removed => 'zinc',
        };
    }
}