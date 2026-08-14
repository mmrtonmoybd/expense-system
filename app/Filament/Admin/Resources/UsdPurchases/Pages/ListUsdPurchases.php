<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Pages;

use App\Filament\Admin\Resources\UsdPurchases\UsdPurchaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListUsdPurchases extends ListRecords
{
    protected static string $resource = UsdPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
