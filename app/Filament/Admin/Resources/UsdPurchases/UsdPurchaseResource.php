<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases;

use App\Filament\Admin\Resources\UsdPurchases\Pages\CreateUsdPurchase;
use App\Filament\Admin\Resources\UsdPurchases\Pages\EditUsdPurchase;
use App\Filament\Admin\Resources\UsdPurchases\Pages\ListUsdPurchases;
use App\Filament\Admin\Resources\UsdPurchases\Pages\ViewUsdPurchase;
use App\Filament\Admin\Resources\UsdPurchases\Schemas\UsdPurchaseForm;
use App\Filament\Admin\Resources\UsdPurchases\Schemas\UsdPurchaseInfolist;
use App\Filament\Admin\Resources\UsdPurchases\Tables\UsdPurchasesTable;
use App\Filament\Admin\Resources\UsdPurchases\Widgets\UsdPurchaseStatsOverview;
use App\Filament\Admin\Resources\UsdPurchases\Widgets\UsdPurchaseTrendChart;
use App\Models\UsdPurchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class UsdPurchaseResource extends Resource
{
    protected static ?string $model = UsdPurchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'purchase_number';

    public static function getModelLabel(): string
    {
        return __('panel.resources.usd_purchase.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.usd_purchase.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Inventory');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['purchase_number', 'source', 'reference'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['Date' => $record->purchase_date?->format((string) config('app.date_format')), 'Source' => $record->source, 'Status' => str($record->status)->headline()->toString()]);
    }

    public static function form(Schema $schema): Schema
    {
        return UsdPurchaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UsdPurchaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsdPurchasesTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && parent::canEdit($record);
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
            UsdPurchaseStatsOverview::class,
            UsdPurchaseTrendChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsdPurchases::route('/'),
            'create' => CreateUsdPurchase::route('/create'),
            'view' => ViewUsdPurchase::route('/{record}'),
            'edit' => EditUsdPurchase::route('/{record}/edit'),
        ];
    }
}
