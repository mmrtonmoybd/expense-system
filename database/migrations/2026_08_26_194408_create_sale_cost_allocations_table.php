<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_cost_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('sale_line_id')->nullable()->constrained('sale_lines')->nullOnDelete();
            $table->foreignId('inventory_layer_id')->nullable()->constrained('inventory_layers')->nullOnDelete();
            $table->foreignId('usd_purchase_id')->nullable()->constrained('usd_purchases')->nullOnDelete();
            $table->string('costing_method', 20);
            $table->decimal('quantity_used', 20, 8);
            $table->decimal('purchase_rate', 20, 8);
            $table->decimal('cost_amount', 20, 2);
            $table->timestamp('consumed_at');
            $table->timestamps();

            $table->index(['sale_id', 'sale_line_id']);
            $table->index('costing_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_cost_allocations');
    }
};
