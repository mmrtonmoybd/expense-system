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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->string('invoice_number')->nullable()->unique();
            $table->date('sale_date')->index();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->decimal('total_usd', 20, 8)->default(0);
            $table->decimal('total_bdt_revenue', 20, 2)->default(0);
            $table->decimal('total_inventory_cost', 20, 2)->default(0);
            $table->decimal('total_gross_profit', 20, 2)->default(0);
            $table->string('status')->default('draft')->index();
            $table->string('approval_status')->default('not_required')->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->index(['customer_id', 'sale_date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
