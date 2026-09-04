<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\Payrolls\PayrollResource;
use App\Filament\Admin\Resources\Payrolls\Widgets\PayrollStatsOverview;
use App\Filament\Admin\Resources\Payrolls\Widgets\PayrollTrendChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPayrolls extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = PayrollResource::class;

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
            PayrollStatsOverview::class,
            PayrollTrendChart::class,
        ];
    }
}
