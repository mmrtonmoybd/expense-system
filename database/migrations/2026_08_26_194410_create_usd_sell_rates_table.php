<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usd_sell_rates', function (Blueprint $table): void {
            $table->id();
            $table->decimal('rate', 20, 8);
            $table->decimal('previous_rate', 20, 8)->nullable();
            $table->timestamp('effective_from')->index();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usd_sell_rates');
    }
};
