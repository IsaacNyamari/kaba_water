<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Enums\ReadingStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reading;
use App\Support\Notifier;

/**
 * In-app notifications (database + live over Reverb) for the day-to-day
 * billing cycle. Kept apart from the accounting observers, which post to the
 * ledger; this one only tells people what happened.
 *
 *  - a client submits a reading       → staff who approve readings
 *  - a reading is approved/rejected   → the client
 *  - a bill is sent                   → the client
 *  - a payment is recorded            → the client (or the office, if the client paid)
 */
class ActivityObserver
{
    /** Readings: created / approved / rejected. */
    public function readingCreated(Reading $reading): void
    {
        if ($reading->status !== ReadingStatus::Pending) {
            return;
        }

        $reading->loadMissing('meter.client');

        Notifier::send(
            Notifier::vendorTeam($reading->vendor_id, 'vendor.readings.manage'),
            'Reading to review from '.($reading->meter?->client?->name ?? 'a client'),
            'Meter '.($reading->meter?->serial ?? '').' · '.number_format((int) $reading->reading).' m³ on '.$reading->read_at->format('d M Y'),
            route('vendor.readings.index', ['status' => 'pending']),
            'clock',
            'reading',
        );
    }

    public function readingUpdated(Reading $reading): void
    {
        if (! $reading->wasChanged('status') || $reading->getOriginal('status') !== ReadingStatus::Pending) {
            return;
        }

        $reading->loadMissing('meter.client');
        $approved = $reading->status === ReadingStatus::Approved;

        Notifier::send(
            Notifier::clientUser($reading->meter?->client),
            $approved ? 'Your reading was approved' : 'Your reading was not accepted',
            $approved
                ? number_format((int) $reading->reading).' m³ on meter '.$reading->meter?->serial.' · '.number_format((int) $reading->consumption).' m³ used.'
                : ($reading->rejection_reason ?: 'Please check the meter and submit the reading again.'),
            route('portal.readings.index'),
            $approved ? 'check-circle' : 'x-circle',
            'reading',
        );
    }

    /** Bills: the client hears when one is sent to them. */
    public function invoiceSaved(Invoice $invoice): void
    {
        $becameSent = $invoice->status === InvoiceStatus::Sent
            && ($invoice->wasRecentlyCreated || $invoice->wasChanged('status'))
            && ! in_array($invoice->getOriginal('status'), [InvoiceStatus::Overdue, InvoiceStatus::Paid], true);

        if (! $becameSent) {
            return;
        }

        $currency = $invoice->vendor?->currency ?? 'KES';

        Notifier::send(
            Notifier::clientUser($invoice->client),
            'New bill: '.$invoice->number,
            $currency.' '.number_format((float) $invoice->total, 2).($invoice->due_at ? ' · due '.$invoice->due_at->format('d M Y') : ''),
            route('portal.invoices.show', $invoice),
            'document-text',
            'invoice',
        );
    }

    /** Payments: a receipt for the client, or an alert for the office when the client paid. */
    public function paymentCreated(Payment $payment): void
    {
        $payment->loadMissing(['client', 'invoice']);
        $currency = $payment->vendor?->currency ?? 'KES';
        $amount = $currency.' '.number_format((float) $payment->amount, 2);
        $for = $payment->invoice ? ' for '.$payment->invoice->number : '';

        if (auth()->user()?->hasRole('client')) {
            Notifier::send(
                Notifier::vendorTeam($payment->vendor_id, 'vendor.payments.manage'),
                'Payment received from '.($payment->client?->name ?? 'a client'),
                $amount.$for.' · '.$payment->method->label(),
                route('vendor.payments.index'),
                'banknotes',
                'payment',
            );

            return;
        }

        Notifier::send(
            Notifier::clientUser($payment->client),
            'Payment received — thank you',
            $amount.$for.' was recorded on '.$payment->paid_at->format('d M Y').'.',
            route('portal.payments.index'),
            'banknotes',
            'payment',
        );
    }
}
