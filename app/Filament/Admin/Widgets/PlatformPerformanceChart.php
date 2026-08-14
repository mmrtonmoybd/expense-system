<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\FinancialReportService;
use Filament\Widgets\ChartWidget;

final class PlatformPerformanceChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Platform sales, revenue and profit';

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
        $rows = app(FinancialReportService::class)
            ->platformPerformanceQuery(now()->startOfMonth(), now())
            ->get();

        return ['datasets' => [
            ['label' => 'USD sold', 'data' => $rows->pluck('usd_sold'), 'backgroundColor' => '#435cea'],
            ['label' => 'Revenue', 'data' => $rows->pluck('revenue'), 'backgroundColor' => '#8741b5'],
            ['label' => 'Gross profit', 'data' => $rows->pluck('gross_profit'), 'backgroundColor' => '#16a34a'],
        ], 'labels' => $rows->pluck('name')];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
