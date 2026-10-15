<?php

namespace App\Support;

/**
 * The roles a vendor can give team members. Permissions per role live in
 * Database\Seeders\RolesAndPermissionsSeeder.
 */
final class TeamRoles
{
    /** @return array<string, array{label: string, icon: string, description: string}> */
    public static function all(): array
    {
        return [
            'vendor_staff' => [
                'label' => 'Office staff',
                'icon' => 'building-office',
                'description' => 'Clients, meters, readings, work orders, invoices and payments.',
            ],
            'technician' => [
                'label' => 'Field technician',
                'icon' => 'wrench-screwdriver',
                'description' => 'Sees the repairs assigned to them and marks them started or done.',
            ],
            'meter_reader' => [
                'label' => 'Meter reader',
                'icon' => 'beaker',
                'description' => 'Records meter readings (with meter photos) and prints invoices. Cannot see revenue totals.',
            ],
            'accountant' => [
                'label' => 'Accountant / clerk',
                'icon' => 'calculator',
                'description' => 'Ledger and reports, M-Pesa reconciliation and suspense, exports and statements; read-only billing and tariffs; approves project costs.',
            ],
            'project_manager' => [
                'label' => 'Project manager',
                'icon' => 'briefcase',
                'description' => 'Runs borehole projects end to end: stages, budget, approvals and closure.',
            ],
            'site_engineer' => [
                'label' => 'Site engineer',
                'icon' => 'map-pin',
                'description' => 'Daily progress reports, site expenses and materials on projects.',
            ],
            'storekeeper' => [
                'label' => 'Storekeeper',
                'icon' => 'cube',
                'description' => 'Receives, issues and returns project materials.',
            ],
            'procurement_officer' => [
                'label' => 'Procurement officer',
                'icon' => 'shopping-cart',
                'description' => 'Purchase orders, deliveries and supplier bills for projects.',
            ],
        ];
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function label(?string $role): string
    {
        return $role === 'vendor' ? 'Director / owner' : (self::all()[$role]['label'] ?? ucfirst((string) $role));
    }
}
