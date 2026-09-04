<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales;

use App\Filament\Admin\Resources\Sales\Pages\CreateSale;
use App\Filament\Admin\Resources\Sales\Pages\EditSale;
use App\Filament\Admin\Resources\Sales\Pages\ListSales;
use App\Filament\Admin\Resources\Sales\Pages\ViewSale;
use App\Filament\Admin\Resources\Sales\Schemas\SaleForm;
use App\Filament\Admin\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\Admin\Resources\Sales\Tables\SalesTable;
use App\Filament\Admin\Resources\Sales\Widgets\SaleBreakdownChart;
use App\Filament\Admin\Resources\Sales\Widgets\SaleStatsOverview;
use App\Filament\Admin\Resources\Sales\Widgets\SaleTrendChart;
use App\Models\Sale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'sale_number';

    public static function getModelLabel(): string
    {
        return __('panel.resources.sale.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.sale.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Sales');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['sale_number', 'invoice_number', 'customer.name', 'customer.phone', 'customer.company_name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('customer:id,name,phone,company_name');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Customer' => $record->customer->name, 'Phone' => $record->customer->phone, 'Status' => str($record->status)->headline()->toString()];
    }

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer:id,name,phone,company_name', 'invoice:id,sale_id,invoice_number']);
    }

    public static function canEdit(Model $record): bool
    {
        return in_array($record->status, ['draft', 'pending'], true)
            && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'draft' && parent::canDelete($record);
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
            SaleBreakdownChart::class,
            SaleStatsOverview::class,
            SaleTrendChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'create' => CreateSale::route('/create'),
            'view' => ViewSale::route('/{record}'),
            'edit' => EditSale::route('/{record}/edit'),
        ];
    }
}
