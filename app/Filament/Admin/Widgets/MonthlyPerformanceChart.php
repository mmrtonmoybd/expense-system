<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\FinancialReportService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class MonthlyPerformanceChart extends ChartWidget
{
    protected static ?int $sort = 8;

    protected ?string $heading = 'Monthly revenue, expense and net profit';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected ?string $pollingInterval = null;

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewProfit') ?? false;
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn (int $offset): Carbon => now()->subMonths($offset));
        $reports = $months->map(fn (Carbon $month): array => app(FinancialReportService::class)->profitAndLoss($month->copy()->startOfMonth(), $month->copy()->endOfMonth()));

        return [
            'datasets' => [
                ['label' => 'Revenue', 'data' => $reports->pluck('revenue'), 'backgroundColor' => '#435cea'],
                ['label' => 'Office expense', 'data' => $reports->pluck('office_expenses'), 'backgroundColor' => '#f59e0b'],
                ['label' => 'Salary expense', 'data' => $reports->pluck('salary_expense'), 'backgroundColor' => '#8741b5'],
                ['label' => 'Net profit', 'data' => $reports->pluck('net_profit'), 'type' => 'line', 'borderColor' => '#16a34a'],
            ],
            'labels' => $months->map->format('M Y'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
