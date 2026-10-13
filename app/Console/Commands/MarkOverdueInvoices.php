<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Flip "sent" invoices whose due date has passed to "overdue".
 * Scheduled daily in routes/console.php.
 */
class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark sent invoices past their due date as overdue';

    public function handle(): int
    {
        $count = Invoice::withoutGlobalScope('vendor')
            ->where('status', InvoiceStatus::Sent->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereColumn('amount_paid', '<', 'total')
            ->update(['status' => InvoiceStatus::Overdue->value, 'updated_at' => now()]);

        $this->info("{$count} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}
