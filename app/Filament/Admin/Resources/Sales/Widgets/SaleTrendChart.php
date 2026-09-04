<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Widgets;

use App\Filament\Admin\Resources\Sales\Pages\ListSales;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;

final class SaleTrendChart extends ChartWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected ?string $heading = 'Sales in view, by day';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Sale') ?? false;
    }

    protected function getTablePage(): string
    {
        return ListSales::class;
    }

    protected function getData(): array
    {
        $rows = $this->summaryQuery()
            ->selectRaw('sale_date, COALESCE(SUM(total_usd), 0) usd, COALESCE(SUM(sell_amount), 0) revenue, COALESCE(SUM(profit_amount), 0) profit')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->limit(180)
            ->get();

        $datasets = [
            ['label' => 'Revenue', 'data' => $rows->pluck('revenue'), 'borderColor' => '#435cea', 'backgroundColor' => '#435cea33'],
            ['label' => 'USD sold', 'data' => $rows->pluck('usd'), 'borderColor' => '#0891b2'],
        ];

        if ($this->canViewProfit()) {
            $datasets[] = ['label' => 'Gross profit', 'data' => $rows->pluck('profit'), 'borderColor' => '#16a34a'];
        }

        return [
            'datasets' => $datasets,
            'labels' => $rows->map(fn (object $row): string => Carbon::parse((string) $row->sale_date)->format((string) config('app.date_format'))),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
