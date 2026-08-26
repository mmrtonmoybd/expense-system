<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_method_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('old_method', 20);
            $table->string('new_method', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->index();
            $table->decimal('usd_balance_snapshot', 20, 8)->default(0);
            $table->decimal('inventory_value_snapshot', 20, 2)->default(0);
            $table->decimal('average_cost_snapshot', 20, 8)->default(0);
            $table->foreignId('opening_layer_id')->nullable()->constrained('inventory_layers')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_method_changes');
    }
};
