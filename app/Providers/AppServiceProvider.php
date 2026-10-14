<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerAccountingObservers();
    }

    /** Auto-posting to the double-entry ledger (see docs/accounting.md). */
    protected function registerAccountingObservers(): void
    {
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);
        \App\Models\Expense::observe(\App\Observers\ExpenseObserver::class);

        // Work order notifications (database + Reverb broadcast).
        \App\Models\WorkOrder::observe(\App\Observers\WorkOrderObserver::class);
        \App\Models\Vendor::observe(\App\Observers\VendorObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
