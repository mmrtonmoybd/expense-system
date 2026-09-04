<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Analytics;

use App\Support\AnalyticsFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

final class CustomerConcentrationChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Customer concentration';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected ?string $maxHeight = '300px';

    protected bool $isCollapsible = true;

    protected function getData(): array
    {
        $filters = AnalyticsFilters::fromPage($this->pageFilters);

        $rows = $filters->applyToSales(
            DB::table('sales')
                ->join('customers', 'customers.id', '=', 'sales.customer_id')
                ->where('sales.status', 'posted')
                ->whereBetween('sales.sale_date', [$filters->from, $filters->to]),
        )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->selectRaw('customers.name, COALESCE(SUM(sales.sell_amount), 0) revenue')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Revenue',
                'data' => $rows->pluck('revenue'),
                'backgroundColor' => ['#435cea', '#8741b5', '#d33572', '#f59e0b', '#16a34a', '#0891b2', '#7c3aed', '#e11d48', '#0284c7', '#ca8a04'],
            ]],
            'labels' => $rows->pluck('name'),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
