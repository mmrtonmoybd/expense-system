<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeSalaryHistory extends Model
{
    protected $fillable = ['employee_id', 'old_salary', 'new_salary', 'effective_at', 'changed_by', 'reason'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return ['old_salary' => 'decimal:2', 'new_salary' => 'decimal:2', 'effective_at' => 'datetime'];
    }
}
