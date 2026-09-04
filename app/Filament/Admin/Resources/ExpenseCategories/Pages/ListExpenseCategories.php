<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseCategories\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Admin\Resources\ExpenseCategories\Widgets\ExpenseCategoryStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListExpenseCategories extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = ExpenseCategoryResource::class;

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
            ExpenseCategoryStatsOverview::class,
        ];
    }
}
