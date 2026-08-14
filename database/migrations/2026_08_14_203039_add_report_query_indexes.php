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
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['status', 'sale_date']);
        });
        Schema::table('usd_purchases', function (Blueprint $table): void {
            $table->index(['status', 'purchase_date']);
        });
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->index(['transaction_type', 'occurred_at']);
        });
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->index(['status', 'payroll_month']);
        });
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['status', 'sale_date']);
        });
        Schema::table('usd_purchases', function (Blueprint $table): void {
            $table->dropIndex(['status', 'purchase_date']);
        });
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropIndex(['transaction_type', 'occurred_at']);
        });
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->dropIndex(['status', 'payroll_month']);
        });
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'occurred_at']);
        });
    }
};
