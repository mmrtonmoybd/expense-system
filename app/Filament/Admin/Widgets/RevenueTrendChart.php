<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\FinancialReportService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class RevenueTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Monthly revenue and profit';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewProfit') ?? false;
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset));
        $reports = $months->map(fn (Carbon $month) => app(FinancialReportService::class)->profitAndLoss($month->copy()->startOfMonth(), $month->copy()->endOfMonth()));

        return ['datasets' => [['label' => 'Revenue', 'data' => $reports->pluck('revenue'), 'borderColor' => '#435cea', 'backgroundColor' => '#435cea33'], ['label' => 'Gross profit', 'data' => $reports->pluck('gross_profit'), 'borderColor' => '#16a34a'], ['label' => 'Net profit', 'data' => $reports->pluck('net_profit'), 'borderColor' => '#d33572']], 'labels' => $months->map->format('M Y')];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
