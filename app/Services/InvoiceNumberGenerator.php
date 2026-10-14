<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceNumberGenerator
{
    /**
     * Generates sequential per-vendor invoice numbers: INV-YYYY-NNNN.
     * Resets the sequence each calendar year.
     */
    public function next(int $vendorId): string
    {
        $prefix = 'INV-'.now()->format('Y').'-';

        $last = Invoice::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
