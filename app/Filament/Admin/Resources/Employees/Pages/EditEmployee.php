<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Pages;

use App\Actions\UpdateEmployeeSalaryAction;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $salary = (string) $data['current_salary'];
        $reason = $data['salary_change_reason'] ?? null;
        unset($data['current_salary'], $data['salary_change_reason']);
        $record->update($data);

        return app(UpdateEmployeeSalaryAction::class)->execute($record, $salary, auth()->user(), $reason);
    }
}
