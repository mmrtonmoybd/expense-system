<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Employee;
use App\Models\EmployeeSalaryHistory;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

final readonly class UpdateEmployeeSalaryAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(Employee $employee, string $salary, User $user, ?string $reason = null): Employee
    {
        return DB::transaction(function () use ($employee, $salary, $user, $reason): Employee {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $oldSalary = (string) $employee->current_salary;
            if (bccomp($oldSalary, $salary, 2) === 0) {
                return $employee;
            }
            $employee->update(['current_salary' => $salary]);
            EmployeeSalaryHistory::query()->create(['employee_id' => $employee->id, 'old_salary' => $oldSalary, 'new_salary' => $salary, 'effective_at' => now(), 'changed_by' => $user->id, 'reason' => $reason]);
            $this->auditLogger->record($user, 'employee_salary_changed', $employee, ['current_salary' => $oldSalary], ['current_salary' => $salary], $reason);

            return $employee->refresh();
        });
    }
}
