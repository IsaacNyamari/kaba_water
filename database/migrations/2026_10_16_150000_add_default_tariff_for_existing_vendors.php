<?php

use App\Models\Tariff;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Give every existing vendor without an active tariff the default flat KES 200 / m³ tariff. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vendors')->pluck('id')->each(fn (int $vendorId) => Tariff::ensureDefaultFor($vendorId));
    }

    public function down(): void
    {
        DB::table('tariffs')
            ->where('name', Tariff::DEFAULT_NAME)
            ->where('type', 'flat')
            ->where('rate', Tariff::DEFAULT_RATE)
            ->delete();
    }
};
