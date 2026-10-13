<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChartOfAccount> */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        $type = fake()->randomElement(AccountType::cases());

        return [
            'vendor_id' => Vendor::factory(),
            'code' => (string) fake()->unique()->numberBetween(6000, 9999),
            'name' => ucfirst(fake()->words(2, true)),
            'type' => $type,
            'normal_side' => $type->normalSide(),
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
