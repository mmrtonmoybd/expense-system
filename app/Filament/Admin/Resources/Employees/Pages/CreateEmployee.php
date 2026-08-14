<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Pages;

use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Models\EmployeeSalaryHistory;
use Filament\Resources\Pages\CreateRecord;

final class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function afterCreate(): void
    {
        EmployeeSalaryHistory::query()->create(['employee_id' => $this->record->id, 'new_salary' => $this->record->current_salary, 'effective_at' => now(), 'changed_by' => auth()->id(), 'reason' => 'Initial salary']);
    }
}
