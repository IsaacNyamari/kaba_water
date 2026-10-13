<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\OpeningBalance;
use App\Models\Payment;
use App\Models\Vendor;
use App\Services\Accounting\ChartOfAccountsService;
use App\Services\Accounting\Exceptions\AccountingException;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\ExpenseRecorded;
use App\Services\Accounting\Postings\InvoiceIssued;
use App\Services\Accounting\Postings\PaymentReceived;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Post invoices, payments and expenses that existed before the ledger.
 * Safe to run repeatedly (postings are idempotent). Documents dated on or
 * before a vendor's posted opening-balance date are skipped — the opening
 * balances already include them.
 */
class AccountingBackfill extends Command
{
    protected $signature = 'accounting:backfill {--vendor= : Only this vendor id}';

    protected $description = 'Post pre-existing invoices, payments and expenses to the double-entry ledger';

    public function handle(LedgerService $ledger, ChartOfAccountsService $chart): int
    {
        $vendors = Vendor::query()->when($this->option('vendor'), fn ($q, $id) => $q->whereKey($id))->get();

        foreach ($vendors as $vendor) {
            $chart->ensureFor($vendor);

            $cutoff = OpeningBalance::withoutGlobalScope('vendor')
                ->where('vendor_id', $vendor->id)->where('is_posted', true)->value('as_of_date');

            $stats = ['posted' => 0, 'skipped' => 0, 'failed' => 0];

            $run = function ($event, $date) use ($ledger, $cutoff, &$stats) {
                if ($cutoff && $date && Carbon::parse($date)->toDateString() <= Carbon::parse($cutoff)->toDateString()) {
                    $stats['skipped']++;

                    return;
                }
                try {
                    $before = $ledger->findPosted($event->meta());
                    $ledger->postEvent($event);
                    $before ? $stats['skipped']++ : $stats['posted']++;
                } catch (AccountingException $e) {
                    $stats['failed']++;
                    $this->warn("  {$e->getMessage()}");
                }
            };

            Invoice::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)
                ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value, InvoiceStatus::Paid->value])
                ->with('reading')->orderBy('issued_at')->each(fn ($i) => $run(new InvoiceIssued($i), $i->issued_at));

            Payment::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)
                ->with('invoice')->orderBy('paid_at')->each(fn ($p) => $run(new PaymentReceived($p), $p->paid_at));

            Expense::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)
                ->with('category')->orderBy('paid_at')->each(fn ($e) => $run(ExpenseRecorded::forExpense($e), $e->paid_at));

            $this->info("{$vendor->name}: {$stats['posted']} posted, {$stats['skipped']} already done/skipped, {$stats['failed']} failed.");
        }

        return self::SUCCESS;
    }
}
