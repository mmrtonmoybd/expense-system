<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Pages;

use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Filament\Admin\Resources\Employees\Widgets\EmployeePayrollChart;
use App\Filament\Admin\Resources\Employees\Widgets\EmployeePayrollStats;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EmployeePayrollStats::class,
            EmployeePayrollChart::class,
        ];
    }
}
