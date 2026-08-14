<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Pages;

use App\Filament\Admin\Resources\UsdPurchases\UsdPurchaseResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditUsdPurchase extends EditRecord
{
    protected static string $resource = UsdPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['bdt_total'] = bcmul((string) $data['usd_quantity'], (string) $data['purchase_rate'], 2);

        return $data;
    }
}
