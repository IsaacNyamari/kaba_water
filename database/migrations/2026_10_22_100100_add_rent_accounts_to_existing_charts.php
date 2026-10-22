<?php

use App\Services\Accounting\ChartOfAccountsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 4300 Rental Income and 4400 Service Charges to every vendor that
 * already has a chart of accounts, so rent invoices can post. Seeding is
 * idempotent: existing codes are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('chart_of_accounts')->distinct()->pluck('vendor_id')
            ->each(fn (int $vendorId) => app(ChartOfAccountsService::class)->seedDefaultsFor($vendorId));
    }

    public function down(): void
    {
        // Only remove the new accounts where nothing has been posted to them.
        DB::table('chart_of_accounts')
            ->whereIn('code', ['4300', '4400'])
            ->where('is_system', true)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_lines')->whereColumn('journal_lines.account_id', 'chart_of_accounts.id'))
            ->delete();
    }
};
