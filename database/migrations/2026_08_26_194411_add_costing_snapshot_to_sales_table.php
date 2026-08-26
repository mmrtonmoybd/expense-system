<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('costing_method', 20)->nullable()->after('total_gross_profit');
            $table->decimal('cost_rate', 20, 8)->nullable()->after('costing_method');
            $table->decimal('cost_amount', 20, 2)->nullable()->after('cost_rate');
            $table->decimal('sell_rate', 20, 8)->nullable()->after('cost_amount');
            $table->decimal('sell_amount', 20, 2)->nullable()->after('sell_rate');
            $table->decimal('profit_amount', 20, 2)->nullable()->after('sell_amount');
            $table->timestamp('costing_calculated_at')->nullable()->after('profit_amount');

            $table->index('costing_method');
            $table->index(['status', 'costing_method']);
        });

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->string('costing_method', 20)->nullable()->after('gross_profit');
            $table->index('costing_method');
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropIndex(['costing_method']);
            $table->dropColumn('costing_method');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['status', 'costing_method']);
            $table->dropIndex(['costing_method']);
            $table->dropColumn(['costing_method', 'cost_rate', 'cost_amount', 'sell_rate', 'sell_amount', 'profit_amount', 'costing_calculated_at']);
        });
    }
};
