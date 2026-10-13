<?php

namespace App\Enums;

enum AssetCategory: string
{
    case Vehicle   = 'vehicle';
    case Pump      = 'pump';
    case Meter     = 'meter';
    case Building  = 'building';
    case Tools     = 'tools';
    case Equipment = 'equipment';
    case Other     = 'other';

    public function label(): string
    {
        return match($this) {
            self::Vehicle   => 'Vehicle',
            self::Pump      => 'Pump',
            self::Meter     => 'Meter',
            self::Building  => 'Building',
            self::Tools     => 'Tools',
            self::Equipment => 'Equipment',
            self::Other     => 'Other',
        };
    }
}