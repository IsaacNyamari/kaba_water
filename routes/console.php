<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the scheduler: run `php artisan schedule:work` locally, or a cron
// entry for `php artisan schedule:run` every minute in production.
Schedule::command('invoices:mark-overdue')->dailyAt('00:15');
Schedule::command('projects:alerts')->dailyAt('07:00');
