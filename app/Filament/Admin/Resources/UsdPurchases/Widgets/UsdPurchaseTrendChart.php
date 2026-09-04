<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Widgets;

use App\Filament\Admin\Resources\UsdPurchases\Pages\ListUsdPurchases;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;

final class UsdPurchaseTrendChart extends ChartWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected ?string $heading = 'Purchases in view, by day';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    protected function getTablePage(): string
    {
        return ListUsdPurchases::class;
    }

    protected function getData(): array
    {
        $rows = $this->summaryQuery()
            ->selectRaw('purchase_date, COALESCE(SUM(usd_quantity), 0) usd, COALESCE(SUM(bdt_total), 0) bdt, CASE WHEN SUM(usd_quantity) > 0 THEN SUM(bdt_total) / SUM(usd_quantity) ELSE 0 END rate')
            ->groupBy('purchase_date')
            ->orderBy('purchase_date')
            ->limit(180)
            ->get();

        return [
            'datasets' => [
                ['label' => 'USD bought', 'data' => $rows->pluck('usd'), 'borderColor' => '#435cea', 'backgroundColor' => '#435cea33'],
                ['label' => 'Average rate', 'data' => $rows->pluck('rate'), 'borderColor' => '#f59e0b'],
            ],
            'labels' => $rows->map(fn (object $row): string => Carbon::parse((string) $row->purchase_date)->format((string) config('app.date_format'))),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
