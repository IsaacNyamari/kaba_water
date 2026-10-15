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
            'vendor.dashboard.view',    // business dashboard (revenue, balances)
            'vendor.team.manage',       // add / change / remove team members
            'vendor.jobs.view',         // "My jobs": work orders assigned to me (start / complete)
            'vendor.clients.view',
            'vendor.clients.manage',
            'vendor.meters.view',
            'vendor.meters.manage',
            'vendor.readings.view',
            'vendor.readings.manage',   // covers create + approve/reject/delete
            'vendor.readings.create',   // record readings only (meter readers)
            'vendor.tariffs.view',
            'vendor.tariffs.manage',
            'vendor.settings.manage',

            // ─── Vendor — Billing ──────────────────────────────────
            'vendor.invoices.view',
            'vendor.invoices.manage',   // covers create, edit, send, void, delete
            'vendor.payments.view',
            'vendor.payments.manage',   // covers record + delete
            'vendor.mpesa.view',        // M-Pesa transactions, STK requests and the SMS log
            'vendor.mpesa.reconcile',   // apply suspense / credit payments to customers
            'vendor.prepaid.view',      // prepaid token sales
            'vendor.prepaid.manage',    // re-issue failed tokens, vending settings

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

            // ─── Accounting (double-entry ledger) ──────────────────
            'accounting.view',
            'accounting.post',            // manual journal entries, reversals, opening balances
            'accounting.close-period',
            'accounting.reopen-period',

            // ─── Project Management (borehole projects) ────────────
            'projects.view',
            'projects.manage',            // create/edit projects, stages, milestones, tasks, team & equipment
            'projects.report',            // submit daily progress reports and technical records
            'projects.expenses.submit',
            'projects.expenses.approve',  // approve / reject / pay expenses, approve progress reports
            'projects.materials.manage',  // orders, receipts, issues, returns, wastage
            'projects.suppliers.manage',  // the project supplier list (separate from billing suppliers)
            'projects.budget.revise',
            'projects.close',             // close and reopen projects

            // ─── Client portal ─────────────────────────────────────
            'client.dashboard.view',
            'client.invoices.view',
            'client.payments.view',
            'client.payments.make',
            'client.readings.view',
            'client.readings.create',      // clients can submit readings
            'client.work_orders.view',     // clients see their own work orders
            'client.work_orders.create',   // clients can request repairs / service
            'client.profile.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ─── Roles ────────────────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $vendor = Role::firstOrCreate(['name' => 'vendor',      'guard_name' => 'web']);
        $client = Role::firstOrCreate(['name' => 'client',      'guard_name' => 'web']);

        // Super admin: only platform.*
        $superAdmin->syncPermissions(
            Permission::where('name', 'like', 'platform.%')->get()
        );

        // Vendor (workspace owner): every vendor.*, accounting.* and projects.* permission
        $vendor->syncPermissions(
            Permission::where('name', 'like', 'vendor.%')
                ->orWhere('name', 'like', 'accounting.%')
                ->orWhere('name', 'like', 'projects.%')
                ->get()
        );

        // ─── Vendor team roles (see App\Support\TeamRoles) ──────────────
        // Accountant / clerk: full accounting, M-Pesa reconciliation (suspense), exports and statements;
        // read-only billing and tariffs (cannot change tariff rates); project cost approval.
        Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web'])->syncPermissions(
            Permission::where('name', 'like', 'accounting.%')
                ->orWhereIn('name', [
                    'vendor.clients.view', 'vendor.tariffs.view', 'vendor.reports.view',
                    'vendor.invoices.view', 'vendor.payments.view', 'vendor.expenses.view', 'vendor.finance.view',
                    'vendor.mpesa.view', 'vendor.mpesa.reconcile', 'vendor.prepaid.view',
                    'projects.view', 'projects.expenses.approve',
                ])
                ->get()
        );

        // ─── Project roles ───────────────────────────────────────────
        // Project manager: runs projects end to end.
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web'])->syncPermissions(
            Permission::where('name', 'like', 'projects.%')->get()
        );

        // Site engineer / supervisor: daily reports, site expenses and materials.
        Role::firstOrCreate(['name' => 'site_engineer', 'guard_name' => 'web'])->syncPermissions([
            'projects.view', 'projects.report', 'projects.expenses.submit', 'projects.materials.manage',
        ]);

        // Storekeeper: receives, issues and returns materials.
        Role::firstOrCreate(['name' => 'storekeeper', 'guard_name' => 'web'])->syncPermissions([
            'projects.view', 'projects.materials.manage',
        ]);

        // Procurement officer: purchase orders, deliveries and supplier bills.
        Role::firstOrCreate(['name' => 'procurement_officer', 'guard_name' => 'web'])->syncPermissions([
            'projects.view', 'projects.materials.manage', 'projects.expenses.submit', 'projects.suppliers.manage',
        ]);

        // Office staff: day-to-day customer work, read-only accounting.
        Role::firstOrCreate(['name' => 'vendor_staff', 'guard_name' => 'web'])->syncPermissions([
            'vendor.dashboard.view',
            'vendor.clients.view', 'vendor.clients.manage',
            'vendor.meters.view', 'vendor.meters.manage',
            'vendor.readings.view', 'vendor.readings.manage', 'vendor.readings.create',
            'vendor.work_orders.view', 'vendor.work_orders.manage', 'vendor.jobs.view',
            'vendor.invoices.view', 'vendor.invoices.manage',
            'vendor.payments.view', 'vendor.payments.manage',
            'vendor.mpesa.view', 'vendor.prepaid.view',
            'accounting.view',
        ]);

        // Field technician: only the jobs assigned to them.
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web'])->syncPermissions([
            'vendor.jobs.view',
        ]);

        // Meter reader: record readings (with meter photos) and print invoices. No revenue totals,
        // no editing past bills, no deleting customers.
        Role::firstOrCreate(['name' => 'meter_reader', 'guard_name' => 'web'])->syncPermissions([
            'vendor.readings.view', 'vendor.readings.create', 'vendor.meters.view', 'vendor.invoices.view',
        ]);

        // Client: every client.* permission
        $client->syncPermissions(
            Permission::where('name', 'like', 'client.%')->get()
        );
    }
}
