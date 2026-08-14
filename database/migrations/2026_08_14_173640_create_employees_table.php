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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->string('name')->index();
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->string('designation');
            $table->date('joining_date');
            $table->decimal('current_salary', 20, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
