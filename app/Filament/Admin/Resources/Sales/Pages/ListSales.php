<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Filament\Admin\Resources\Sales\Widgets\SaleStatsOverview;
use App\Filament\Admin\Resources\Sales\Widgets\SaleTrendChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSales extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = SaleResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SaleStatsOverview::class,
            SaleTrendChart::class,
        ];
    }
}
