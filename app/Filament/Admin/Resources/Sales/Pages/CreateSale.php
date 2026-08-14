<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Pages;

use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Support\TransactionNumberGenerator;
use Filament\Resources\Pages\CreateRecord;

final class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['sale_number'] = app(TransactionNumberGenerator::class)->next('sale', $data['sale_date']);
        $data['status'] = 'draft';
        $data['approval_status'] = 'not_required';
        $data['created_by'] = auth()->id();
        $data['total_usd'] = 0;
        $data['total_bdt_revenue'] = 0;
        $data['total_inventory_cost'] = 0;
        $data['total_gross_profit'] = 0;

        return $data;
    }
}
