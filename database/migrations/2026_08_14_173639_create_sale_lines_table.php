<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->decimal('usd_quantity', 20, 8);
            $table->decimal('selling_rate', 20, 8);
            $table->decimal('weighted_cost_rate', 20, 8);
            $table->decimal('revenue', 20, 2);
            $table->decimal('inventory_cost', 20, 2);
            $table->decimal('gross_profit', 20, 2);
            $table->boolean('is_rate_overridden')->default(false);
            $table->foreignId('rate_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rate_override_reason')->nullable();
            $table->text('note')->nullable();
            $table->index(['platform_id', 'sale_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
    }
};
