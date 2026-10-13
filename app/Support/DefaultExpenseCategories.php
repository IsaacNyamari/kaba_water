<?php

namespace App\Support;

class DefaultExpenseCategories
{
    public static function all(): array
    {
        return [
            ['name' => 'Water Purchase',       'slug' => 'water-purchase',  'color' => 'blue'],
            ['name' => 'Salaries & Wages',     'slug' => 'salaries',        'color' => 'violet'],
            ['name' => 'Rent',                 'slug' => 'rent',            'color' => 'amber'],
            ['name' => 'Electricity',          'slug' => 'electricity',     'color' => 'yellow'],
            ['name' => 'Fuel & Transport',     'slug' => 'fuel',            'color' => 'orange'],
            ['name' => 'Repairs & Maintenance', 'slug' => 'repairs',         'color' => 'rose'],
            ['name' => 'Meter & Fittings',     'slug' => 'meter-fittings',  'color' => 'emerald'],
            ['name' => 'Office & Admin',       'slug' => 'office',          'color' => 'zinc'],
            ['name' => 'Taxes & Licenses',     'slug' => 'taxes',           'color' => 'red'],
            ['name' => 'Miscellaneous',        'slug' => 'misc',            'color' => 'slate'],
        ];
    }

    /** Seed defaults for a given vendor. Idempotent. */
    public static function seedFor(int $vendorId): void
    {
        foreach (self::all() as $cat) {
            \App\Models\ExpenseCategory::firstOrCreate(
                ['vendor_id' => $vendorId, 'slug' => $cat['slug']],
                [
                    'name'       => $cat['name'],
                    'color'      => $cat['color'],
                    'is_system'  => true,
                ]
            );
        }
    }
}
