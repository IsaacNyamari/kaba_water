<?php

namespace Database\Factories;

use App\Models\FiscalPeriod;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FiscalPeriod> */
class FiscalPeriodFactory extends Factory
{
    protected $model = FiscalPeriod::class;

    public function definition(): array
    {
        $start = now()->startOfQuarter();

        return [
            'vendor_id' => Vendor::factory(),
            'name' => $start->format('Y').'-Q'.$start->quarter,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfQuarter()->toDateString(),
            'is_closed' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(['is_closed' => true, 'closed_at' => now()]);
    }
}
