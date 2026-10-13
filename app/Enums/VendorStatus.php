<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Trial     = 'trial';
    case Active    = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::Trial     => 'Trial',
            self::Active    => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    /** Flux badge color token */
    public function color(): string
    {
        return match($this) {
            self::Trial     => 'blue',
            self::Active    => 'emerald',
            self::Suspended => 'red',
        };
    }
}