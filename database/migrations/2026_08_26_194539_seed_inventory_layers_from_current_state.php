<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The pre-existing engine tracked inventory only as an aggregate moving-average
     * position, so FIFO/LIFO has no layer history to start from. Snapshot the current
     * position into a single opening layer so that the sum of remaining layer quantities
     * always equals the authoritative USD balance. Historical profit is untouched.
     */
    public function up(): void
    {
        if (DB::table('inventory_layers')->exists()) {
            return;
        }

        $state = DB::table('inventory_states')->first();

        if ($state === null || bccomp((string) $state->usd_balance, '0', 8) !== 1) {
            return;
        }

        DB::table('inventory_layers')->insert([
            'origin_type' => 'opening_snapshot',
            'source_type' => 'App\Models\InventoryState',
            'source_id' => $state->id,
            'usd_purchase_id' => null,
            'purchase_rate' => $state->weighted_average_cost,
            'original_quantity' => $state->usd_balance,
            'remaining_quantity' => $state->usd_balance,
            'original_cost_bdt' => $state->inventory_value_bdt,
            'remaining_cost_bdt' => $state->inventory_value_bdt,
            'is_closed' => false,
            'acquired_at' => now(),
            'created_by' => null,
            'note' => 'Opening layer created from the existing moving weighted average inventory position.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('inventory_layers')->where('origin_type', 'opening_snapshot')->delete();
    }
};
