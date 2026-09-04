<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\InventoryTransactions\InventoryTransactionResource;
use App\Filament\Admin\Resources\InventoryTransactions\Widgets\InventoryFlowChart;
use App\Filament\Admin\Resources\InventoryTransactions\Widgets\InventoryFlowStatsOverview;
use Filament\Resources\Pages\ListRecords;

final class ListInventoryTransactions extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = InventoryTransactionResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InventoryFlowStatsOverview::class,
            InventoryFlowChart::class,
        ];
    }
}
