<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Filament\Admin\Resources\Expenses\Widgets\ExpenseCategoryChart;
use App\Filament\Admin\Resources\Expenses\Widgets\ExpenseStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListExpenses extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = ExpenseResource::class;

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
            ExpenseStatsOverview::class,
            ExpenseCategoryChart::class,
        ];
    }
}
