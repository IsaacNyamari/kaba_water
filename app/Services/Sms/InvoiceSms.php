<?php

namespace App\Services\Sms;

use App\Models\Integration;
use App\Models\Invoice;
use App\Models\SmsMessage;

/**
 * The SMS a client receives when an invoice is issued: who it is from, the
 * reading and units used, the amount and due date, and how to pay
 * (short pay link, plus Paybill / Till details when M-Pesa is connected).
 */
class InvoiceSms
{
    public function __construct(protected SmsService $sms) {}

    public function send(Invoice $invoice): SmsMessage
    {
        return $this->sms->send(
            $invoice->vendor_id,
            $invoice->client?->phone,
            $this->message($invoice),
            'invoice',
            $invoice->client,
        );
    }

    public function message(Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'vendor', 'reading.meter']);
        $reading = $invoice->reading;

        $lines = [
            "{$invoice->vendor?->name}: water bill {$invoice->number}",
            "Dear {$invoice->client?->name} (A/C {$invoice->client?->account_no}),",
        ];

        if ($reading) {
            $lines[] = 'Meter '.$reading->meter?->serial.': '.number_format((int) $reading->previous_reading).' to '.number_format((int) $reading->reading)
                .' = '.number_format((int) $reading->consumption).' m3 ('.$reading->read_at?->format('M Y').')';
        }

        $lines[] = 'Amount due: KES '.number_format($invoice->balance(), 2).($invoice->due_at ? ' by '.$invoice->due_at->format('d M Y') : '');
        $lines[] = 'Pay: '.$invoice->shortPayUrl();
        if ($mpesa = Integration::active($invoice->vendor_id, Integration::MPESA)) {
            $lines[] = $mpesa->setting('shortcode_type') === 'till'
                ? 'Pay via M-Pesa Till '.$mpesa->setting('shortcode')
                : 'Pay via M-Pesa Paybill '.$mpesa->setting('shortcode').', Account '.$invoice->client?->account_no;
        }

        return implode("\n", $lines);
    }
}
