<?php

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Enums\VendorStatus;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Vendor> */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Water';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'email' => fake()->unique()->companyEmail(),
            'business_type' => BusinessType::Water,
            'currency' => 'KES',
            'status' => VendorStatus::Active,
            'trial_ends_at' => now()->addDays(30),
        ];
    }

    /** A landlord who bills tenants per house number. */
    public function propertyOwner(): static
    {
        return $this->state(fn () => [
            'name' => fake()->unique()->company().' Apartments',
            'business_type' => BusinessType::Property,
        ]);
    }
}
