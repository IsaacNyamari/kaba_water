<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    /**
     * Render the invoice to a raw PDF string.
     */
    public function render(Invoice $invoice): string
    {
        $invoice->load(['items', 'client', 'vendor', 'unit.property', 'reading.meter', 'payments']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }

    /**
     * Store the PDF on the private disk and record its path on the invoice.
     */
    public function store(Invoice $invoice): string
    {
        $path = "invoices/{$invoice->vendor_id}/{$invoice->number}.pdf";

        Storage::disk('local')->put($path, $this->render($invoice));

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    /**
     * Force regeneration of the stored PDF (e.g. after status change).
     */
    public function regenerate(Invoice $invoice): string
    {
        return $this->store($invoice);
    }

    /**
     * Return an absolute path to the PDF.
     *
     * By default this regenerates on every call so status changes
     * (draft → sent → paid) are always reflected. Set $regenerate = false
     * to reuse the cached file.
     */
    public function path(Invoice $invoice, bool $regenerate = false): string
    {
        $needsRegen = $regenerate
            || ! $invoice->pdf_path
            || ! Storage::disk('local')->exists($invoice->pdf_path);

        if ($needsRegen) {
            $this->store($invoice);
            $invoice->refresh();
        }

        return Storage::disk('local')->path($invoice->pdf_path);
    }
}
