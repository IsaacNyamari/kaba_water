<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Vendor;
use App\Services\Accounting\ChartOfAccountsService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Unposted by default. Use ->balanced() for a posted two-line entry:
 *   JournalEntry::factory()->for($vendor)->balanced('250.00', '1000', '4000')->create();
 *
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'date' => now()->toDateString(),
            'reference' => 'JE-'.fake()->unique()->numerify('#####'),
            'memo' => fake()->sentence(4),
        ];
    }

    /** Add a debit and a matching credit line, then post the entry. */
    public function balanced(string $amount = '100.00', string $debitCode = '1000', string $creditCode = '4000'): static
    {
        return $this->afterCreating(function (JournalEntry $entry) use ($amount, $debitCode, $creditCode) {
            app(ChartOfAccountsService::class)->ensureFor($entry->vendor_id);

            $account = fn (string $code) => ChartOfAccount::withoutGlobalScope('vendor')
                ->where('vendor_id', $entry->vendor_id)->where('code', $code)->value('id');

            $entry->lines()->create(['account_id' => $account($debitCode), 'debit' => $amount, 'credit' => '0']);
            $entry->lines()->create(['account_id' => $account($creditCode), 'debit' => '0', 'credit' => $amount]);
            $entry->forceFill(['posted_at' => now()])->save();
        });
    }
}
