<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumns('sales', ['costing_method', 'cost_rate', 'cost_amount', 'sell_rate', 'sell_amount', 'profit_amount', 'costing_calculated_at'])) {
            return;
        }

        DB::table('sales')
            ->whereNull('costing_method')
            ->whereIn('status', ['posted', 'reversed'])
            ->update([
                'costing_method' => 'average',
                'cost_amount' => DB::raw('total_inventory_cost'),
                'sell_amount' => DB::raw('total_bdt_revenue'),
                'profit_amount' => DB::raw('total_gross_profit'),
                'cost_rate' => DB::raw('CASE WHEN total_usd > 0 THEN total_inventory_cost / total_usd ELSE 0 END'),
                'sell_rate' => DB::raw('CASE WHEN total_usd > 0 THEN total_bdt_revenue / total_usd ELSE 0 END'),
                'costing_calculated_at' => DB::raw('COALESCE(posted_at, updated_at)'),
            ]);

        DB::table('sale_lines')
            ->whereNull('costing_method')
            ->whereIn('sale_id', DB::table('sales')->where('costing_method', 'average')->select('id'))
            ->update(['costing_method' => 'average']);
    }

    public function down(): void {}
};
