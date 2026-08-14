<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Pages;

use App\Filament\Admin\Resources\UsdPurchases\UsdPurchaseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewUsdPurchase extends ViewRecord
{
    protected static string $resource = UsdPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
