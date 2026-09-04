<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers;

use App\Filament\Admin\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Admin\Resources\Customers\Pages\EditCustomer;
use App\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use App\Filament\Admin\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Admin\Resources\Customers\RelationManagers\CustomerPlatformsRelationManager;
use App\Filament\Admin\Resources\Customers\RelationManagers\RateHistoriesRelationManager;
use App\Filament\Admin\Resources\Customers\RelationManagers\SalesRelationManager;
use App\Filament\Admin\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Admin\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Admin\Resources\Customers\Tables\CustomersTable;
use App\Filament\Admin\Resources\Customers\Widgets\CustomerAccountStats;
use App\Filament\Admin\Resources\Customers\Widgets\CustomerRevenueChart;
use App\Filament\Admin\Resources\Customers\Widgets\CustomerSalesTrendChart;
use App\Filament\Admin\Resources\Customers\Widgets\CustomerStatsOverview;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('panel.resources.customer.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.customer.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Customers');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'company_name', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->name} ({$record->phone})";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['Phone' => $record->phone, 'Company' => $record->company_name]);
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CustomerPlatformsRelationManager::class,
            RateHistoriesRelationManager::class,
            SalesRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            CustomerAccountStats::class,
            CustomerSalesTrendChart::class,
            CustomerStatsOverview::class,
            CustomerRevenueChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
