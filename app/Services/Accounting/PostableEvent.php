<?php

namespace App\Services\Accounting;

/**
 * A business event that can be posted to the ledger.
 *
 * lines(): list of ['account_code' => '1100', 'debit' => '100.00', 'credit' => '0',
 *                   'client_id' => ?, 'meter_id' => ?, 'description' => ?]
 *          (account_id may be given instead of account_code)
 * meta():  ['vendor_id', 'date', 'reference', 'memo',
 *           'source_type', 'source_id', 'source_event', 'created_by']
 */
interface PostableEvent
{
    /** @return list<array<string, mixed>> */
    public function lines(): array;

    /** @return array<string, mixed> */
    public function meta(): array;
}
