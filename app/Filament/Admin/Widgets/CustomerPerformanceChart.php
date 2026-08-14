<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\FinancialReportService;
use Filament\Widgets\ChartWidget;

final class CustomerPerformanceChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Top customer profitability';

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
            ->customerPerformanceQuery(now()->startOfMonth(), now())
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                ['label' => 'Revenue', 'data' => $rows->pluck('revenue'), 'backgroundColor' => '#435cea'],
                ['label' => 'Gross profit', 'data' => $rows->pluck('gross_profit'), 'backgroundColor' => '#16a34a'],
            ],
            'labels' => $rows->pluck('name'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
