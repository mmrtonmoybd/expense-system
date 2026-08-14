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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_number')->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('payroll_month')->index();
            $table->decimal('base_salary', 20, 2);
            $table->decimal('allowance', 20, 2)->default(0);
            $table->decimal('bonus', 20, 2)->default(0);
            $table->decimal('deduction', 20, 2)->default(0);
            $table->decimal('adjustment', 20, 2)->default(0);
            $table->decimal('total_salary_expense', 20, 2);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('due_amount', 20, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->unique(['employee_id', 'payroll_month']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
