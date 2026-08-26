<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_layers', function (Blueprint $table): void {
            $table->id();
            $table->string('origin_type')->comment('purchase, opening_balance, positive_adjustment, reversal, method_change_rebase');
            $table->nullableMorphs('source');
            $table->foreignId('usd_purchase_id')->nullable()->constrained('usd_purchases')->nullOnDelete();
            $table->decimal('purchase_rate', 20, 8);
            $table->decimal('original_quantity', 20, 8);
            $table->decimal('remaining_quantity', 20, 8);
            $table->decimal('original_cost_bdt', 20, 2);
            $table->decimal('remaining_cost_bdt', 20, 2);
            $table->boolean('is_closed')->default(false);
            $table->timestamp('acquired_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['is_closed', 'acquired_at', 'id'], 'inventory_layers_consumption_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_layers');
    }
};
