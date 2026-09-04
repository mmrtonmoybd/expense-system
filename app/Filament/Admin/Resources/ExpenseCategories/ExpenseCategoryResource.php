<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseCategories;

use App\Filament\Admin\Resources\ExpenseCategories\Pages\CreateExpenseCategory;
use App\Filament\Admin\Resources\ExpenseCategories\Pages\EditExpenseCategory;
use App\Filament\Admin\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Admin\Resources\ExpenseCategories\Pages\ViewExpenseCategory;
use App\Filament\Admin\Resources\ExpenseCategories\Schemas\ExpenseCategoryForm;
use App\Filament\Admin\Resources\ExpenseCategories\Schemas\ExpenseCategoryInfolist;
use App\Filament\Admin\Resources\ExpenseCategories\Tables\ExpenseCategoriesTable;
use App\Filament\Admin\Resources\ExpenseCategories\Widgets\ExpenseCategoryStatsOverview;
use App\Models\ExpenseCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Tag;

    protected static string|UnitEnum|null $navigationGroup = 'Expenses';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('panel.resources.expense_category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.expense_category.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Expenses');
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExpenseCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [
            ExpenseCategoryStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseCategories::route('/'),
            'create' => CreateExpenseCategory::route('/create'),
            'view' => ViewExpenseCategory::route('/{record}'),
            'edit' => EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
