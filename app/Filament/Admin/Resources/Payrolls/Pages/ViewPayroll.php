<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Pages;

use App\Filament\Admin\Resources\Payrolls\PayrollResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
