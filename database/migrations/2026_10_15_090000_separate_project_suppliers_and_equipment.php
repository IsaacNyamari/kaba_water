<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make Project Management independent of Water Billing: projects get their
 * own suppliers and their own equipment register instead of borrowing the
 * billing module's suppliers and accounting assets.
 *
 * Existing project rows that point at billing suppliers / assets are copied
 * into the new tables first, so no project history is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 40)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('address')->nullable();
            $table->string('kra_pin', 20)->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'name']);
        });

        Schema::create('project_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('identifier', 80)->nullable(); // plate / serial number
            $table->string('type', 60)->nullable();       // rig, compressor, pump, truck…
            $table->string('ownership', 10)->default('owned'); // owned | hired
            $table->foreignId('project_supplier_id')->nullable()->constrained('project_suppliers')->nullOnDelete();
            $table->decimal('default_rate', 15, 2)->default(0);
            $table->string('rate_unit', 10)->default('day');
            $table->string('status', 20)->default('available'); // available | maintenance | retired
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        // ── Copy borrowed billing suppliers into project suppliers ──────────
        $supplierIds = DB::table('project_expenses')->whereNotNull('supplier_id')->pluck('supplier_id')
            ->merge(DB::table('project_material_movements')->whereNotNull('supplier_id')->pluck('supplier_id'))
            ->unique()->values();

        $supplierMap = [];
        foreach (DB::table('suppliers')->whereIn('id', $supplierIds)->get() as $s) {
            $existing = DB::table('project_suppliers')->where('vendor_id', $s->vendor_id)->where('name', $s->name)->value('id');
            $supplierMap[$s->id] = $existing ?? DB::table('project_suppliers')->insertGetId([
                'vendor_id' => $s->vendor_id,
                'name' => $s->name,
                'phone' => $s->phone ?? null,
                'email' => $s->email ?? null,
                'address' => $s->address ?? null,
                'notes' => $s->notes ?? null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Copy borrowed assets into the project equipment register ────────
        $equipmentMap = [];
        $assetIds = DB::table('project_team_assignments')->whereNotNull('asset_id')->pluck('asset_id')->unique();
        foreach (DB::table('assets')->whereIn('id', $assetIds)->get() as $a) {
            $equipmentMap[$a->id] = DB::table('project_equipment')->insertGetId([
                'vendor_id' => $a->vendor_id,
                'name' => $a->name,
                'identifier' => $a->identifier ?? null,
                'type' => $a->category ?? null,
                'ownership' => 'owned',
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Re-point project expenses and material movements ────────────────
        foreach (['project_expenses', 'project_material_movements'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign(['supplier_id']));

            // One CASE update remaps every id at once, so an old id never collides with a new one.
            if ($supplierMap !== []) {
                $case = 'CASE supplier_id '.collect($supplierMap)->map(fn ($new, $old) => 'WHEN '.(int) $old.' THEN '.(int) $new)->implode(' ').' END';
                DB::table($tableName)->whereIn('supplier_id', array_keys($supplierMap))->update(['supplier_id' => DB::raw($case)]);
            }
            // Anything pointing at a supplier that no longer exists is unlinked (the payee text stays).
            DB::table($tableName)->whereNotNull('supplier_id')->whereNotIn('supplier_id', DB::table('project_suppliers')->select('id'))->update(['supplier_id' => null]);

            Schema::table($tableName, fn (Blueprint $table) => $table->foreign('supplier_id')->references('id')->on('project_suppliers')->nullOnDelete());
        }

        // ── Labour & equipment: asset_id → equipment_id ─────────────────────
        Schema::table('project_team_assignments', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('user_id')->constrained('project_equipment')->nullOnDelete();
        });
        foreach ($equipmentMap as $assetId => $equipmentId) {
            DB::table('project_team_assignments')->where('asset_id', $assetId)->update(['equipment_id' => $equipmentId]);
        }
        Schema::table('project_team_assignments', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_team_assignments', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
        Schema::table('project_team_assignments', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
            $table->dropColumn('equipment_id');
        });

        foreach (['project_expenses', 'project_material_movements'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign(['supplier_id']));
            // Project suppliers have no billing equivalent; keep the payee text, drop the link.
            DB::table($tableName)->update(['supplier_id' => null]);
            Schema::table($tableName, fn (Blueprint $table) => $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete());
        }

        Schema::dropIfExists('project_equipment');
        Schema::dropIfExists('project_suppliers');
    }
};
