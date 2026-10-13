<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, InvoicePdfService $pdf): Response
    {
        $this->authorize('downloadPdf', $invoice);

        // Always regenerate so the badge, totals, and PAID stamp
        // reflect the current state of the invoice.
        $path = $pdf->path($invoice, regenerate: true);

        $filename = "{$invoice->number}.pdf";

        return response()->file($path, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
