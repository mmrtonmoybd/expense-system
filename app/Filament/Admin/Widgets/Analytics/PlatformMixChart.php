<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Analytics;

use App\Support\AnalyticsFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PlatformMixChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Platform mix';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected ?string $maxHeight = '300px';

    protected bool $isCollapsible = true;

    protected function getData(): array
    {
        $filters = AnalyticsFilters::fromPage($this->pageFilters);
        $canViewProfit = auth()->user()?->can('ViewProfit') ?? false;

        $rows = $filters->applyToSales(
            DB::table('sale_lines')
                ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
                ->join('platforms', 'platforms.id', '=', 'sale_lines.platform_id')
                ->where('sales.status', 'posted')
                ->whereBetween('sales.sale_date', [$filters->from, $filters->to]),
        )
            ->when($filters->platformId, fn (Builder $query, int $id): Builder => $query->where('sale_lines.platform_id', $id))
            ->groupBy('platforms.id', 'platforms.name')
            ->orderByDesc('revenue')
            ->limit(12)
            ->selectRaw('platforms.name, COALESCE(SUM(sale_lines.usd_quantity), 0) usd, COALESCE(SUM(sale_lines.revenue), 0) revenue, COALESCE(SUM(sale_lines.gross_profit), 0) profit')
            ->get();

        $datasets = [
            ['label' => 'USD sold', 'data' => $rows->pluck('usd'), 'backgroundColor' => '#435cea'],
            ['label' => 'Revenue', 'data' => $rows->pluck('revenue'), 'backgroundColor' => '#8741b5'],
        ];

        if ($canViewProfit) {
            $datasets[] = ['label' => 'Gross profit', 'data' => $rows->pluck('profit'), 'backgroundColor' => '#16a34a'];
        }

        return ['datasets' => $datasets, 'labels' => $rows->pluck('name')];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
