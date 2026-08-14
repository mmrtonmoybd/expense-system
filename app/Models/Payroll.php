<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Payroll extends Model
{
    /** @use HasFactory<\Database\Factories\PayrollFactory> */
    use HasFactory;

    protected $fillable = ['payroll_number', 'employee_id', 'payroll_month', 'base_salary', 'allowance', 'bonus', 'deduction', 'adjustment', 'total_salary_expense', 'paid_amount', 'due_amount', 'payment_date', 'status', 'notes'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    protected function casts(): array
    {
        return ['payroll_month' => 'date', 'base_salary' => 'decimal:2', 'allowance' => 'decimal:2', 'bonus' => 'decimal:2', 'deduction' => 'decimal:2', 'adjustment' => 'decimal:2', 'total_salary_expense' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_amount' => 'decimal:2', 'payment_date' => 'date'];
    }
}
