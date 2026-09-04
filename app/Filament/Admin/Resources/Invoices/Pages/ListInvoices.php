<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\Invoices\Widgets\InvoiceStatsOverview;
use Filament\Resources\Pages\ListRecords;

final class ListInvoices extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = InvoiceResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InvoiceStatsOverview::class,
        ];
    }
}
