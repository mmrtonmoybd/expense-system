<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Pages;

use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Filament\Admin\Resources\Sales\Widgets\SaleBreakdownChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

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
            SaleBreakdownChart::class,
        ];
    }
}
