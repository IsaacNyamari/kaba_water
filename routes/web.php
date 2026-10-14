<?php

use App\Http\Controllers\Auth\PhoneLoginController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

// Marketing site. `/` keeps the name `home` because the auth layouts link to it.
Route::view('/', 'marketing.home')->name('home');

// Search engines: generated from Admin → Settings → SEO.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');

Route::name('marketing.')->group(function () {
    Route::view('/services', 'marketing.services')->name('services');
    Route::redirect('/features', '/services', 301)->name('features');
    Route::view('/pricing', 'marketing.pricing')->name('pricing');
    Route::view('/about', 'marketing.about')->name('about');
    Route::view('/contact', 'marketing.contact')->name('contact');
    Route::view('/terms', 'marketing.terms')->name('terms');
    Route::view('/privacy', 'marketing.privacy')->name('privacy');
});

/*
|--------------------------------------------------------------------------
| Access requests (self-registration is disabled)
|--------------------------------------------------------------------------
| Anyone can ask for access; an admin (or the automatic email check, when
| ACCESS_AUTO_APPROVE=true) approves, and a single-use signed signup link is
| emailed. The accept page validates the signature itself (Livewire's own
| update requests don't carry it).
*/

Route::middleware('guest')->group(function () {
    // Clients without an email: phone number + meter number + PIN.
    Route::post('/login/phone', [PhoneLoginController::class, 'store'])->name('login.phone');

    Route::livewire('/request-access', 'access.request')->name('access.request');
    Route::livewire('/access/{accessRequest}/accept', 'access.accept')->name('access.accept');
});

/*
|--------------------------------------------------------------------------
| Legacy /dashboard shim — role-aware redirect
|--------------------------------------------------------------------------
| Any bookmark or email link to /dashboard lands the user in the right place.
*/

Route::get('/dashboard', function () {
    return auth()->check()
        ? redirect(auth()->user()->homeRoute())
        : redirect()->route('login');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Vendor billing notice — must be OUTSIDE the sub.active group
|--------------------------------------------------------------------------
| EnsureSubscriptionActive redirects here when a subscription is inactive.
| If this route were inside that group, it would cause a redirect loop.
*/

Route::middleware(['auth', 'verified', 'role:vendor|vendor_staff|technician|meter_reader|accountant|project_manager|site_engineer|storekeeper|procurement_officer', 'vendor.context'])->group(function () {
    Route::livewire('/vendor/billing/notice', 'vendor.billing.notice')->name('vendor.billing.notice');
});

/*
|--------------------------------------------------------------------------
| Super Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::livewire('/', 'admin.dashboard')->name('dashboard');

        // Vendors
        Route::livewire('/vendors', 'admin.vendors.index')->name('vendors.index');
        Route::livewire('/vendors/create', 'admin.vendors.form')->name('vendors.create');
        Route::livewire('/vendors/{vendor}/edit', 'admin.vendors.form')->name('vendors.edit');

        // Access requests
        Route::livewire('/access-requests', 'admin.access-requests.index')->name('access-requests.index');

        // Plans
        Route::livewire('/plans', 'admin.plans.index')->name('plans.index');
        Route::livewire('/plans/create', 'admin.plans.form')->name('plans.create');
        Route::livewire('/plans/{plan}/edit', 'admin.plans.form')->name('plans.edit');

        // Platform settings (.env + branding) — re-confirm the password first.
        Route::livewire('/settings', 'admin.settings.index')->middleware('password.confirm')->name('settings.index');
    });

/*
|--------------------------------------------------------------------------
| Vendor workspace
|--------------------------------------------------------------------------
*/

// The vendor's whole team (owner, office staff, technicians, meter readers,
// accountants) enters here; every page below checks its own permission.
Route::middleware(['auth', 'verified', 'role:vendor|vendor_staff|technician|meter_reader|accountant', 'vendor.context', 'sub.active'])
    ->prefix('vendor')->name('vendor.')
    ->group(function () {

        // Business dashboard (revenue, balances) — owner and office staff
        Route::livewire('/', 'vendor.dashboard')->middleware('permission:vendor.dashboard.view')->name('dashboard');

        // ── My jobs (technicians) ───────────────────────────
        Route::livewire('/jobs', 'vendor.jobs.index')->middleware('permission:vendor.jobs.view')->name('jobs.index');

        // ── Team ────────────────────────────────────────────
        Route::livewire('/team', 'vendor.team.index')->middleware('permission:vendor.team.manage')->name('team.index');

        // ── Clients ─────────────────────────────────────────
        Route::middleware('permission:vendor.clients.view|vendor.clients.manage')->group(function () {
            Route::livewire('/clients', 'vendor.clients.index')->name('clients.index');
            Route::livewire('/clients/create', 'vendor.clients.form')->name('clients.create');
            Route::livewire('/clients/create/upload', 'vendor.clients.upload')->name('clients.upload');
            Route::livewire('/clients/{client}/edit', 'vendor.clients.form')->name('clients.edit');
        });

        // ── Meters ──────────────────────────────────────────
        Route::middleware('permission:vendor.meters.view|vendor.meters.manage')->group(function () {
            Route::livewire('/meters', 'vendor.meters.index')->name('meters.index');
            Route::livewire('/meters/create', 'vendor.meters.form')->name('meters.create');
            Route::livewire('/meters/{meter}/edit', 'vendor.meters.form')->name('meters.edit');
        });

        // ── Readings ────────────────────────────────────────
        Route::middleware('permission:vendor.readings.view|vendor.readings.manage')->group(function () {
            Route::livewire('/readings', 'vendor.readings.index')->name('readings.index');
            Route::livewire('/readings/create', 'vendor.readings.create')->name('readings.create');
        });

        // ── Work Orders ─────────────────────────────────────
        Route::middleware('permission:vendor.work_orders.view|vendor.work_orders.manage')->group(function () {
            Route::livewire('/work-orders', 'vendor.work-orders.index')->name('work-orders.index');
            Route::livewire('/work-orders/create', 'vendor.work-orders.form')->name('work-orders.create');
            Route::livewire('/work-orders/{workOrder}/edit', 'vendor.work-orders.form')->name('work-orders.edit');
        });

        // ── Invoices ────────────────────────────────────────
        Route::middleware('permission:vendor.invoices.view|vendor.invoices.manage')->group(function () {
            Route::livewire('/invoices', 'vendor.invoices.index')->name('invoices.index');
            Route::livewire('/invoices/create', 'vendor.invoices.create')->name('invoices.create');
            Route::livewire('/invoices/{invoice}', 'vendor.invoices.show')->name('invoices.show');
            Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');
        });

        // ── Payments ────────────────────────────────────────
        Route::middleware('permission:vendor.payments.view|vendor.payments.manage')->group(function () {
            Route::livewire('/payments', 'vendor.payments.index')->name('payments.index');
            Route::livewire('/payments/create', 'vendor.payments.create')->name('payments.create');
        });

        // ── Tariffs ─────────────────────────────────────────
        Route::middleware('permission:vendor.tariffs.view|vendor.tariffs.manage')->group(function () {
            Route::livewire('/tariffs', 'vendor.tariffs.index')->name('tariffs.index');
            Route::livewire('/tariffs/create', 'vendor.tariffs.form')->name('tariffs.create');
            Route::livewire('/tariffs/{tariff}/edit', 'vendor.tariffs.form')->name('tariffs.edit');
        });

        // ── Expenses ────────────────────────────────────────
        Route::middleware('permission:vendor.expenses.view|vendor.expenses.manage')->group(function () {
            Route::livewire('/expenses', 'vendor.expenses.index')->name('expenses.index');
            Route::livewire('/expenses/create', 'vendor.expenses.form')->name('expenses.create');
            Route::livewire('/expenses/{expense}/edit', 'vendor.expenses.form')->name('expenses.edit');
            Route::livewire('/expenses/categories', 'vendor.expenses.categories')->name('expenses.categories');
        });

        // ── Suppliers ───────────────────────────────────────
        Route::middleware('permission:vendor.suppliers.view|vendor.suppliers.manage')->group(function () {
            Route::livewire('/suppliers', 'vendor.suppliers.index')->name('suppliers.index');
            Route::livewire('/suppliers/create', 'vendor.suppliers.form')->name('suppliers.create');
            Route::livewire('/suppliers/{supplier}/edit', 'vendor.suppliers.form')->name('suppliers.edit');
        });

        // ── Assets ──────────────────────────────────────────
        Route::middleware('permission:vendor.assets.view|vendor.assets.manage')->group(function () {
            Route::livewire('/assets', 'vendor.assets.index')->name('assets.index');
            Route::livewire('/assets/create', 'vendor.assets.form')->name('assets.create');
            Route::livewire('/assets/{asset}/edit', 'vendor.assets.form')->name('assets.edit');
        });

        // ── Liabilities ─────────────────────────────────────
        Route::middleware('permission:vendor.liabilities.view|vendor.liabilities.manage')->group(function () {
            Route::livewire('/liabilities', 'vendor.liabilities.index')->name('liabilities.index');
            Route::livewire('/liabilities/create', 'vendor.liabilities.form')->name('liabilities.create');
            Route::livewire('/liabilities/{liability}/edit', 'vendor.liabilities.form')->name('liabilities.edit');
        });

        // ── Financial Reports ───────────────────────────────
        Route::middleware('permission:vendor.finance.view')->group(function () {
            Route::livewire('/finance/reports', 'vendor.finance.reports')->name('finance.reports');
        });

        // ── Usage Reports ───────────────────────────────────
        Route::middleware('permission:vendor.reports.view')->group(function () {
            Route::livewire('/reports', 'vendor.reports.index')->name('reports.index');
        });

        // ── Settings ────────────────────────────────────────
        Route::middleware('permission:vendor.settings.manage')->group(function () {
            Route::livewire('/settings', 'vendor.settings.index')->name('settings.index');
        });
    });

/*
|--------------------------------------------------------------------------
| Vendor accounting (double-entry ledger)
|--------------------------------------------------------------------------
| Open to the workspace owner (vendor) plus accountant / vendor_staff roles.
| Page-level permissions: accounting.view | .post | .close-period | .reopen-period
*/

Route::middleware(['auth', 'verified', 'role:vendor|vendor_staff|technician|meter_reader|accountant', 'vendor.context', 'sub.active', 'permission:accounting.view'])
    ->prefix('vendor/accounting')->name('vendor.accounting.')
    ->group(function () {
        Route::livewire('/', 'vendor.accounting.dashboard')->name('dashboard');
        Route::livewire('/accounts', 'vendor.accounting.accounts.index')->name('accounts.index');
        Route::livewire('/accounts/create', 'vendor.accounting.accounts.form')->name('accounts.create');
        Route::livewire('/accounts/{account}/edit', 'vendor.accounting.accounts.form')->name('accounts.edit');

        Route::livewire('/journal', 'vendor.accounting.journal.index')->name('journal.index');
        Route::livewire('/journal/create', 'vendor.accounting.journal.form')->name('journal.create');
        Route::livewire('/journal/{entry}', 'vendor.accounting.journal.show')->name('journal.show');

        Route::livewire('/periods', 'vendor.accounting.periods.index')->name('periods.index');
        Route::livewire('/opening-balances', 'vendor.accounting.opening-balances')->name('opening-balances');

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::livewire('/trial-balance', 'vendor.accounting.reports.trial-balance')->name('trial-balance');
            Route::livewire('/income-statement', 'vendor.accounting.reports.income-statement')->name('income-statement');
            Route::livewire('/balance-sheet', 'vendor.accounting.reports.balance-sheet')->name('balance-sheet');
            Route::livewire('/cash-flow', 'vendor.accounting.reports.cash-flow')->name('cash-flow');
            Route::livewire('/general-ledger', 'vendor.accounting.reports.general-ledger')->name('general-ledger');
        });
    });

/*
|--------------------------------------------------------------------------
| Module portal + Borehole Project Management
|--------------------------------------------------------------------------
| /modules lists the workspace modules the user can open. Project pages are
| guarded by projects.* permissions and the ProjectPolicy (vendor-owned only).
*/

Route::middleware(['auth', 'verified', 'vendor.context', 'sub.active'])->group(function () {
    Route::livewire('/modules', 'modules')->name('modules');

    Route::middleware('permission:projects.view')->prefix('projects')->name('projects.')->group(function () {
        Route::livewire('/', 'projects.index')->name('index');
        Route::livewire('/create', 'projects.form')->middleware('permission:projects.manage')->name('create');

        // The project module's own suppliers and equipment (independent of Water Billing).
        Route::livewire('/suppliers', 'projects.suppliers.index')->name('suppliers.index');
        Route::livewire('/equipment', 'projects.equipment.index')->name('equipment.index');

        Route::livewire('/{project}', 'projects.show')->whereNumber('project')->name('show');
        Route::livewire('/{project}/edit', 'projects.form')->whereNumber('project')->middleware('permission:projects.manage')->name('edit');
    });
});

/*
|--------------------------------------------------------------------------
| Client portal
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:client', 'vendor.context'])
    ->prefix('portal')->name('portal.')
    ->group(function () {

        Route::livewire('/', 'portal.dashboard')->name('dashboard');
        Route::livewire('/meter', 'portal.meter')->name('meter');

        // Readings
        Route::middleware('permission:client.readings.view|client.readings.create')->group(function () {
            Route::livewire('/readings', 'portal.readings.index')->name('readings.index');
        });
        Route::middleware('permission:client.readings.create')->group(function () {
            Route::livewire('/readings/create', 'portal.readings.create')->name('readings.create');
        });

        // Invoices
        Route::middleware('permission:client.invoices.view')->group(function () {
            Route::livewire('/invoices', 'portal.invoices.index')->name('invoices.index');
            Route::livewire('/invoices/{invoice}', 'portal.invoices.show')->name('invoices.show');
            Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');
        });

        // Payments
        Route::middleware('permission:client.payments.view')->group(function () {
            Route::livewire('/payments', 'portal.payments.index')->name('payments.index');
        });

        // Work Orders (client's own)
        Route::middleware('permission:client.work_orders.view')->group(function () {
            Route::livewire('/work-orders', 'portal.work-orders.index')->name('work-orders.index');
        });
        Route::middleware('permission:client.work_orders.create')->group(function () {
            Route::livewire('/work-orders/create', 'portal.work-orders.create')->name('work-orders.create');
        });

        // Profile
        Route::middleware('permission:client.profile.manage')->group(function () {
            Route::livewire('/profile', 'portal.profile')->name('profile');
        });
    });

/*
|--------------------------------------------------------------------------
| Starter kit settings routes (profile, password, appearance)
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
