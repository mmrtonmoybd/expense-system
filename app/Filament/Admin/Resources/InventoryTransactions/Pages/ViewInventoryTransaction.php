<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions\Pages;

use App\Filament\Admin\Resources\InventoryTransactions\InventoryTransactionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewInventoryTransaction extends ViewRecord
{
    protected static string $resource = InventoryTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
