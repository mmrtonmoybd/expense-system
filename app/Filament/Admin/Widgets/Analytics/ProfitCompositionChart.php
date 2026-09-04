<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Analytics;

use App\Services\FinancialReportService;
use App\Support\AnalyticsFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

final class ProfitCompositionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Revenue, cost and profit over time';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewProfit') ?? false;
    }

    protected function getData(): array
    {
        $filters = AnalyticsFilters::fromPage($this->pageFilters);
        $reports = app(FinancialReportService::class);
        $labels = [];
        $revenue = [];
        $cost = [];
        $gross = [];
        $net = [];

        foreach ($filters->periods() as $start) {
            $report = $reports->profitAndLoss($start->copy()->max($filters->from), $filters->periodEnd($start)->min($filters->to));
            $labels[] = $filters->periodLabel($start);
            $revenue[] = $report['revenue'];
            $cost[] = $report['cost_of_usd_sold'];
            $gross[] = $report['gross_profit'];
            $net[] = $report['net_profit'];
        }

        return [
            'datasets' => [
                ['label' => 'Revenue', 'data' => $revenue, 'backgroundColor' => '#435cea', 'borderColor' => '#435cea'],
                ['label' => 'Cost of USD sold', 'data' => $cost, 'backgroundColor' => '#f59e0b', 'borderColor' => '#f59e0b'],
                ['label' => 'Gross profit', 'data' => $gross, 'type' => 'line', 'borderColor' => '#16a34a'],
                ['label' => 'Net profit', 'data' => $net, 'type' => 'line', 'borderColor' => '#d33572'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
