<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices;

use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Admin\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Admin\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\Admin\Resources\Invoices\Widgets\InvoiceStatsOverview;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getModelLabel(): string
    {
        return __('panel.resources.invoice.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.invoice.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Sales');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'sale.sale_number', 'sale.customer.name', 'sale.customer.phone', 'sale.customer.company_name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('sale.customer:id,name,phone,company_name');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Sale' => $record->sale->sale_number, 'Customer' => $record->sale->customer->name, 'Phone' => $record->sale->customer->phone];
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['sale.customer:id,name,phone,company_name', 'sale.lines.platform:id,name']);
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
            InvoiceStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
