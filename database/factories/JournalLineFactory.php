<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JournalLine> */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => ChartOfAccount::factory(),
            'debit' => '0',
            'credit' => '0',
            'description' => fake()->sentence(3),
        ];
    }

    public function debit(string $amount): static
    {
        return $this->state(['debit' => $amount, 'credit' => '0']);
    }

    public function credit(string $amount): static
    {
        return $this->state(['debit' => '0', 'credit' => $amount]);
    }
}
