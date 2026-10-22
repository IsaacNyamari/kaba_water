<?php

namespace App\Enums;

/**
 * What kind of business a workspace runs. It changes the words the app uses
 * (clients vs tenants) and which pages it puts first; every feature stays
 * available to both.
 */
enum BusinessType: string
{
    /** Sells metered water to clients. */
    case Water = 'water';

    /** Lets houses, apartments and shops and bills each unit (rent, service charge, utilities). */
    case Property = 'property';

    public function label(): string
    {
        return match ($this) {
            self::Water => 'Water vendor',
            self::Property => 'Property / apartment owner',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Water => 'Bill clients for the water their meters record.',
            self::Property => 'Bill tenants per house, apartment or shop: rent, service charge and metered water.',
        };
    }

    /** Singular name for the people billed: "client" or "tenant". */
    public function customerNoun(): string
    {
        return match ($this) {
            self::Water => 'client',
            self::Property => 'tenant',
        };
    }

    /** Name of the billing module in the switcher and module portal. */
    public function moduleName(): string
    {
        return match ($this) {
            self::Water => 'HS Water Billing',
            self::Property => 'HS Property Billing',
        };
    }

    public function moduleDescription(): string
    {
        return match ($this) {
            self::Water => 'Clients, meters, readings, bills, payments, repairs and accounting.',
            self::Property => 'Properties, units, tenants, rent and utility bills, payments and accounting.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Water => 'beaker',
            self::Property => 'building-office-2',
        };
    }
}
