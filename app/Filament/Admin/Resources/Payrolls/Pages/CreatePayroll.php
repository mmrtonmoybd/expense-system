<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Pages;

use App\Filament\Admin\Resources\Payrolls\PayrollResource;
use App\Models\Employee;
use App\Support\PayrollCalculator;
use App\Support\TransactionNumberGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class CreatePayroll extends CreateRecord
{
    protected static string $resource = PayrollResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['base_salary'] = Employee::query()->findOrFail($data['employee_id'])->current_salary;
        $data['payroll_month'] = Carbon::parse($data['payroll_month'])->startOfMonth()->toDateString();
        $data['payroll_number'] = app(TransactionNumberGenerator::class)->next('payroll', $data['payroll_month']);
        $data['total_salary_expense'] = app(PayrollCalculator::class)->total($data);

        if (bccomp($data['total_salary_expense'], '0', 2) === -1) {
            throw ValidationException::withMessages(['data.deduction' => 'Total salary expense cannot be negative.']);
        }

        $data['paid_amount'] = '0.00';
        $data['due_amount'] = $data['total_salary_expense'];
        $data['status'] = 'unpaid';

        return $data;
    }
}
