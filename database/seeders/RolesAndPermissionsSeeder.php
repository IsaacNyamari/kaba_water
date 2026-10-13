<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [

            // ─── Platform (super admin only) ───────────────────────
            'platform.manage_vendors',
            'platform.view_revenue',
            'platform.suspend_vendor',
            'platform.manage_subscriptions',
            'platform.manage_plans',
            'platform.manage_access_requests',

            // ─── Vendor — Workspace ────────────────────────────────
            'vendor.clients.view',
            'vendor.clients.manage',
            'vendor.meters.view',
            'vendor.meters.manage',
            'vendor.readings.view',
            'vendor.readings.manage',   // covers create + approve/reject/delete
            'vendor.tariffs.view',
            'vendor.tariffs.manage',
            'vendor.settings.manage',

            // ─── Vendor — Billing ──────────────────────────────────
            'vendor.invoices.view',
            'vendor.invoices.manage',   // covers create, edit, send, void, delete
            'vendor.payments.view',
            'vendor.payments.manage',   // covers record + delete

            // ─── Vendor — Operations ───────────────────────────────
            'vendor.work_orders.view',
            'vendor.work_orders.manage',   // covers create, edit, assign, complete, delete

            // ─── Vendor — Finance ──────────────────────────────────
            'vendor.expenses.view',
            'vendor.expenses.manage',
            'vendor.suppliers.view',
            'vendor.suppliers.manage',
            'vendor.assets.view',
            'vendor.assets.manage',
            'vendor.expense_categories.manage',
            'vendor.liabilities.view',
            'vendor.liabilities.manage',
            'vendor.finance.view',         // see P&L / balance sheet / cash flow
            'vendor.reports.view',         // usage + revenue analytics (existing)

            // ─── Client portal ─────────────────────────────────────
            'client.dashboard.view',
            'client.invoices.view',
            'client.payments.view',
            'client.payments.make',
            'client.readings.view',
            'client.readings.create',      // clients can submit readings
            'client.work_orders.view',     // clients see their own work orders
            'client.profile.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ─── Roles ────────────────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $vendor     = Role::firstOrCreate(['name' => 'vendor',      'guard_name' => 'web']);
        $client     = Role::firstOrCreate(['name' => 'client',      'guard_name' => 'web']);

        // Super admin: only platform.*
        $superAdmin->syncPermissions(
            Permission::where('name', 'like', 'platform.%')->get()
        );

        // Vendor: every vendor.* permission
        $vendor->syncPermissions(
            Permission::where('name', 'like', 'vendor.%')->get()
        );

        // Client: every client.* permission
        $client->syncPermissions(
            Permission::where('name', 'like', 'client.%')->get()
        );
    }
}
