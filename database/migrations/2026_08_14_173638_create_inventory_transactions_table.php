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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type')->index();
            $table->morphs('source');
            $table->decimal('usd_in', 20, 8)->default(0);
            $table->decimal('usd_out', 20, 8)->default(0);
            $table->decimal('cost_rate', 20, 8)->default(0);
            $table->decimal('bdt_value_in', 20, 2)->default(0);
            $table->decimal('bdt_value_out', 20, 2)->default(0);
            $table->decimal('resulting_usd_balance', 20, 8);
            $table->decimal('resulting_inventory_value_bdt', 20, 2);
            $table->decimal('resulting_weighted_average_cost', 20, 8);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
