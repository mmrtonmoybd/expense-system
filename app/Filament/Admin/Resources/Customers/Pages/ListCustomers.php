<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Resources\Customers\Widgets\CustomerRevenueChart;
use App\Filament\Admin\Resources\Customers\Widgets\CustomerStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCustomers extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = CustomerResource::class;

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
            CustomerStatsOverview::class,
            CustomerRevenueChart::class,
        ];
    }
}
