<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\UsdPurchases\UsdPurchaseResource;
use App\Filament\Admin\Resources\UsdPurchases\Widgets\UsdPurchaseStatsOverview;
use App\Filament\Admin\Resources\UsdPurchases\Widgets\UsdPurchaseTrendChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListUsdPurchases extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = UsdPurchaseResource::class;

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
            UsdPurchaseStatsOverview::class,
            UsdPurchaseTrendChart::class,
        ];
    }
}
