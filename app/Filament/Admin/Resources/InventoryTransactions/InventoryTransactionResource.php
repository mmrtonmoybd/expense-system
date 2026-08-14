<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions;

use App\Filament\Admin\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Filament\Admin\Resources\InventoryTransactions\Pages\ViewInventoryTransaction;
use App\Filament\Admin\Resources\InventoryTransactions\Schemas\InventoryTransactionInfolist;
use App\Filament\Admin\Resources\InventoryTransactions\Tables\InventoryTransactionsTable;
use App\Models\InventoryTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::QueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return InventoryTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryTransactions::route('/'),
            'view' => ViewInventoryTransaction::route('/{record}'),
        ];
    }
}
