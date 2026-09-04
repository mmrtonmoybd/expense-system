<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Pages;

use App\Filament\Admin\Resources\UsdPurchases\UsdPurchaseResource;
use App\Support\Money;
use App\Support\TransactionNumberGenerator;
use Filament\Resources\Pages\CreateRecord;

final class CreateUsdPurchase extends CreateRecord
{
    protected static string $resource = UsdPurchaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['purchase_number'] = app(TransactionNumberGenerator::class)->next('purchase', $data['purchase_date']);
        $data['bdt_total'] = Money::amount(Money::usd((string) $data['usd_quantity']), Money::rate((string) $data['purchase_rate']));
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }
}
