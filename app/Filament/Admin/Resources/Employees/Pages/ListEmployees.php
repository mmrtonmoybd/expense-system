<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Filament\Admin\Resources\Employees\Widgets\EmployeeStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListEmployees extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = EmployeeResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EmployeeStatsOverview::class,
        ];
    }
}
