<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseCategories\Pages;

use App\Filament\Admin\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewExpenseCategory extends ViewRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
