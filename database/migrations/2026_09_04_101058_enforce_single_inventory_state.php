<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The inventory position is a singleton ledger row.
 *
 * It used to be created lazily with firstOrCreate, so two concurrent postings could each
 * insert their own row and split the ledger in half. The row is now created up front and a
 * unique index makes a second one impossible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('inventory_states')->count();

        if ($rows > 1) {
            throw new RuntimeException('inventory_states holds more than one row. Reconcile the duplicate inventory positions manually before running this migration.');
        }

        if ($rows === 0) {
            DB::table('inventory_states')->insert([
                'usd_balance' => 0,
                'inventory_value_bdt' => 0,
                'weighted_average_cost' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('inventory_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('singleton')->default(1)->after('id');
            $table->unique('singleton');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_states', function (Blueprint $table): void {
            $table->dropUnique(['singleton']);
            $table->dropColumn('singleton');
        });
    }
};
