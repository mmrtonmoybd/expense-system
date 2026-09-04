<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Analytics;

use App\Support\AnalyticsFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

final class InventoryPositionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Dollar flow and closing position';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewInternalCosts') ?? false;
    }

    protected function getData(): array
    {
        $filters = AnalyticsFilters::fromPage($this->pageFilters);
        $labels = [];
        $bought = [];
        $sold = [];
        $closing = [];
        $averageCost = [];

        foreach ($filters->periods() as $start) {
            $periodStart = $start->copy()->max($filters->from);
            $periodEnd = $filters->periodEnd($start)->min($filters->to);

            $flow = (array) (DB::table('inventory_transactions')
                ->whereBetween('occurred_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
                ->selectRaw('COALESCE(SUM(usd_in), 0) usd_in, COALESCE(SUM(usd_out), 0) usd_out')
                ->first() ?? []);

            $last = DB::table('inventory_transactions')
                ->where('occurred_at', '<=', $periodEnd->copy()->endOfDay())
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first(['resulting_usd_balance', 'resulting_weighted_average_cost']);

            $labels[] = $filters->periodLabel($start);
            $bought[] = $flow['usd_in'] ?? 0;
            $sold[] = $flow['usd_out'] ?? 0;
            $closing[] = $last->resulting_usd_balance ?? 0;
            $averageCost[] = $last->resulting_weighted_average_cost ?? 0;
        }

        return [
            'datasets' => [
                ['label' => 'USD in', 'data' => $bought, 'backgroundColor' => '#16a34a', 'borderColor' => '#16a34a'],
                ['label' => 'USD out', 'data' => $sold, 'backgroundColor' => '#d33572', 'borderColor' => '#d33572'],
                ['label' => 'Closing USD', 'data' => $closing, 'type' => 'line', 'borderColor' => '#435cea'],
                ['label' => 'Average cost', 'data' => $averageCost, 'type' => 'line', 'borderColor' => '#f59e0b'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
