<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions\Pages;

use App\Filament\Admin\Resources\InventoryTransactions\InventoryTransactionResource;
use Filament\Resources\Pages\ListRecords;

final class ListInventoryTransactions extends ListRecords
{
    protected static string $resource = InventoryTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
