<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Accounting\Exceptions\InvalidAccountException;
use App\Services\Accounting\Exceptions\UnbalancedEntryException;
use App\Support\Money\MoneyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The only way money gets into the books.
 *
 * Invariants enforced here:
 *  - every entry has ≥ 2 lines; each line is a debit OR a credit, never both
 *  - Σ debits = Σ credits exactly (2-dp money via brick/money, no floats)
 *  - every account belongs to the entry's vendor and is active
 *  - the entry date is not inside a closed fiscal period
 *  - posted entries are never edited or deleted, only reversed
 */
class LedgerService
{
    public function __construct(
        protected FiscalPeriodService $periods,
        protected ChartOfAccountsService $chart,
    ) {}

    /**
     * Validate and post a balanced journal entry.
     *
     * @param  list<array<string, mixed>>  $lines  see PostableEvent::lines()
     * @param  array<string, mixed>  $meta  see PostableEvent::meta(); vendor_id and date required
     *
     * @throws UnbalancedEntryException|InvalidAccountException|Exceptions\ClosedPeriodException
     */
    public function post(array $lines, array $meta): JournalEntry
    {
        $vendorId = (int) ($meta['vendor_id'] ?? 0);
        if ($vendorId <= 0) {
            throw new InvalidArgumentException('A journal entry needs a vendor_id.');
        }

        $date = CarbonImmutable::parse($meta['date'] ?? now())->startOfDay();

        $this->chart->ensureFor($vendorId);
        $normalized = $this->normalizeLines($vendorId, $lines);
        $this->assertBalanced($normalized);
        $this->periods->assertOpen($vendorId, $date);

        return DB::transaction(function () use ($vendorId, $date, $meta, $normalized) {
            $entry = JournalEntry::withoutGlobalScope('vendor')->create([
                'vendor_id' => $vendorId,
                'date' => $date->toDateString(),
                'reference' => $meta['reference'] ?? null,
                'memo' => $meta['memo'] ?? null,
                'source_type' => $meta['source_type'] ?? null,
                'source_id' => $meta['source_id'] ?? null,
                'source_event' => $meta['source_event'] ?? null,
                'created_by' => $meta['created_by'] ?? auth()->id(),
                'reverses_id' => $meta['reverses_id'] ?? null,
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            // Posting is the last step: from here on the entry is immutable.
            $entry->forceFill(['posted_at' => now()])->save();

            return $entry->load('lines');
        });
    }

    /**
     * Post a PostableEvent once. If the same source + event was already posted
     * (and not reversed) the existing entry is returned instead — observers can
     * fire repeatedly without double-booking.
     */
    public function postEvent(PostableEvent $event): JournalEntry
    {
        $meta = $event->meta();

        if ($existing = $this->findPosted($meta)) {
            return $existing;
        }

        return $this->post($event->lines(), $meta);
    }

    /** The live (posted, not reversed) entry for a source + event, if any. */
    public function findPosted(array $meta): ?JournalEntry
    {
        if (empty($meta['source_type']) || empty($meta['source_id'])) {
            return null;
        }

        return JournalEntry::withoutGlobalScope('vendor')
            ->where('vendor_id', $meta['vendor_id'])
            ->where('source_type', $meta['source_type'])
            ->where('source_id', $meta['source_id'])
            ->where('source_event', $meta['source_event'] ?? null)
            ->whereNotNull('posted_at')
            ->whereNull('reversed_by_id')
            ->first();
    }

    /**
     * Reverse a posted entry with a mirror entry (debits ↔ credits).
     * The original is never deleted; the two are linked both ways.
     */
    public function reverse(JournalEntry $entry, string $reason, ?\DateTimeInterface $date = null): JournalEntry
    {
        if (! $entry->isPosted()) {
            throw new LogicException('Only posted entries can be reversed.');
        }
        if ($entry->isReversed()) {
            throw new LogicException("Journal entry #{$entry->id} has already been reversed.");
        }

        $entry->loadMissing('lines');
        $date = CarbonImmutable::parse($date ?? now());
        // Never date a reversal before the original.
        if ($date->lt($entry->date)) {
            $date = CarbonImmutable::parse($entry->date);
        }

        $lines = $entry->lines->map(fn ($l) => [
            'account_id' => $l->account_id,
            'client_id' => $l->client_id,
            'meter_id' => $l->meter_id,
            'debit' => (string) $l->credit,
            'credit' => (string) $l->debit,
            'description' => $l->description,
        ])->all();

        return DB::transaction(function () use ($entry, $lines, $reason, $date) {
            $reversal = $this->post($lines, [
                'vendor_id' => $entry->vendor_id,
                'date' => $date,
                'reference' => $entry->reference,
                'memo' => "Reversal of entry #{$entry->id}: {$reason}",
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'source_event' => $entry->source_event ? $entry->source_event.'.reversal' : 'reversal',
                'reverses_id' => $entry->id,
            ]);

            $entry->forceFill(['reversed_by_id' => $reversal->id])->save();

            return $reversal;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines  normalized lines
     *
     * @throws UnbalancedEntryException
     */
    public function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new UnbalancedEntryException('A journal entry needs at least two non-zero lines.');
        }

        $dr = MoneyMath::sumColumn($lines, 'debit');
        $cr = MoneyMath::sumColumn($lines, 'credit');

        if (MoneyMath::isZero($dr)) {
            throw new UnbalancedEntryException('A journal entry cannot total zero.');
        }

        // Amounts are exact 2-dp decimals, so "within 0.001" means "equal".
        if (! MoneyMath::equals($dr, $cr)) {
            throw new UnbalancedEntryException("Debits ({$dr}) do not equal credits ({$cr}).");
        }
    }

    /**
     * Resolve accounts, coerce amounts to 2-dp strings and drop all-zero lines.
     *
     * @return list<array<string, mixed>>
     */
    protected function normalizeLines(int $vendorId, array $lines): array
    {
        $codes = collect($lines)->pluck('account_code')->filter()->unique()->values();
        $ids = collect($lines)->pluck('account_id')->filter()->unique()->values();

        $accounts = ChartOfAccount::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->where(fn ($q) => $q->whereIn('code', $codes)->orWhereIn('id', $ids))
            ->get();

        $out = [];

        foreach ($lines as $i => $line) {
            $account = isset($line['account_id'])
                ? $accounts->firstWhere('id', (int) $line['account_id'])
                : $accounts->firstWhere('code', (string) ($line['account_code'] ?? ''));

            if (! $account) {
                $ref = $line['account_id'] ?? $line['account_code'] ?? '?';
                throw new InvalidAccountException('Line '.($i + 1).": account {$ref} does not exist for this vendor.");
            }
            if (! $account->is_active) {
                throw new InvalidAccountException("Account {$account->code} {$account->name} is inactive.");
            }

            $debit = MoneyMath::str($line['debit'] ?? '0');
            $credit = MoneyMath::str($line['credit'] ?? '0');

            if (MoneyMath::isNegative($debit) || MoneyMath::isNegative($credit)) {
                throw new UnbalancedEntryException('Line '.($i + 1).': amounts cannot be negative.');
            }
            if (! MoneyMath::isZero($debit) && ! MoneyMath::isZero($credit)) {
                throw new UnbalancedEntryException('Line '.($i + 1).': a line is either a debit or a credit, not both.');
            }
            if (MoneyMath::isZero($debit) && MoneyMath::isZero($credit)) {
                continue; // e.g. a zero tax line
            }

            $out[] = [
                'account_id' => $account->id,
                'client_id' => $line['client_id'] ?? null,
                'meter_id' => $line['meter_id'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'description' => isset($line['description']) ? mb_substr((string) $line['description'], 0, 255) : null,
            ];
        }

        return $out;
    }
}
