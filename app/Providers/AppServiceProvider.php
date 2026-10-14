<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reading;
use App\Models\Vendor;
use App\Models\WorkOrder;
use App\Observers\ActivityObserver;
use App\Observers\ExpenseObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Observers\VendorObserver;
use App\Observers\WorkOrderObserver;
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
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);
        Expense::observe(ExpenseObserver::class);

        // In-app notifications (database + live over Reverb).
        WorkOrder::observe(WorkOrderObserver::class);

        $activity = ActivityObserver::class;
        Reading::created(fn ($reading) => app($activity)->readingCreated($reading));
        Reading::updated(fn ($reading) => app($activity)->readingUpdated($reading));
        Invoice::saved(fn ($invoice) => app($activity)->invoiceSaved($invoice));
        Payment::created(fn ($payment) => app($activity)->paymentCreated($payment));
        Vendor::observe(VendorObserver::class);
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
