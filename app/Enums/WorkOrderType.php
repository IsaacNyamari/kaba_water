<?php

namespace App\Enums;

enum WorkOrderType: string
{
    case Repair         = 'repair';
    case Installation   = 'installation';
    case Maintenance    = 'maintenance';
    case Inspection     = 'inspection';
    case Disconnection  = 'disconnection';
    case Reconnection   = 'reconnection';
    case Other          = 'other';

    public function label(): string
    {
        return match($this) {
            self::Repair        => 'Repair',
            self::Installation  => 'Installation',
            self::Maintenance   => 'Maintenance',
            self::Inspection    => 'Inspection',
            self::Disconnection => 'Disconnection',
            self::Reconnection  => 'Reconnection',
            self::Other         => 'Other',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Repair        => 'wrench-screwdriver',
            self::Installation  => 'plus-circle',
            self::Maintenance   => 'cog-6-tooth',
            self::Inspection    => 'magnifying-glass',
            self::Disconnection => 'x-circle',
            self::Reconnection  => 'arrow-path',
            self::Other         => 'clipboard-document',
        };
    }
}