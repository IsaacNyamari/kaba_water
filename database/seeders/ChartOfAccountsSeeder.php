<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Services\Accounting\ChartOfAccountsService;
use Illuminate\Database\Seeder;

/** Seed (or top up) the standard chart for every vendor. Idempotent. */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(ChartOfAccountsService $chart): void
    {
        Vendor::query()->each(fn (Vendor $vendor) => $chart->seedDefaultsFor($vendor));
    }
}
