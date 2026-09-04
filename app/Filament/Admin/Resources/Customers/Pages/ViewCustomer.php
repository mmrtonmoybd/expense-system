<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Pages;

use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Resources\Customers\Widgets\CustomerAccountStats;
use App\Filament\Admin\Resources\Customers\Widgets\CustomerSalesTrendChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CustomerAccountStats::class,
            CustomerSalesTrendChart::class,
        ];
    }
}
