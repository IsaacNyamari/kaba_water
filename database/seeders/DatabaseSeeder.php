<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use App\Models\Vendor;
use App\Support\DefaultExpenseCategories;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $starter = Plan::firstOrCreate(['slug' => 'starter'], [
            'name' => 'Starter',
            'monthly_price' => 1500,
            'max_clients' => 100,
            'max_meters' => 100,
        ]);
        Plan::firstOrCreate(['slug' => 'growth'], [
            'name' => 'Growth',
            'monthly_price' => 4500,
            'max_clients' => 1000,
            'max_meters' => 1000,
        ]);

        // Super admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@swb.test'],
            ['name' => 'Platform Admin', 'password' => Hash::make('password')]
        );
        $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()])->save();
        $admin->syncRoles(['super_admin']);

        // Demo vendor + vendor user
        $vendor = Vendor::firstOrCreate(['slug' => 'demo-water'], [
            'name'          => 'Demo Water Co.',
            'email'         => 'hello@demowater.test',
            'phone'         => '+254700000000',
            'status'        => 'active',
            'plan_id'       => $starter->id,
            'trial_ends_at' => now()->addDays(30),
        ]);

        DefaultExpenseCategories::seedFor($vendor->id);

        $vendorUser = User::firstOrCreate(
            ['email' => 'vendor@swb.test'],
            [
                'vendor_id' => $vendor->id,
                'name'      => 'Vendor Owner',
                'password'  => Hash::make('password'),
            ]
        );
        // vendor_id/email_verified_at are set explicitly so re-running the seeder
        // also repairs a user created by the earlier (broken) version.
        $vendorUser->forceFill([
            'vendor_id'         => $vendor->id,
            'email_verified_at' => $vendorUser->email_verified_at ?? now(),
        ])->save();
        $vendorUser->syncRoles(['vendor']);
    }
}
