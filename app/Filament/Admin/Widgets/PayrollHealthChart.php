<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Payroll;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class PayrollHealthChart extends ChartWidget
{
    protected static ?int $sort = 7;

    protected ?string $heading = 'Payroll expense, paid and due';

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
        $months = collect(range(5, 0))
            ->map(fn (int $offset): Carbon => now()->subMonths($offset)->startOfMonth());
        $payroll = Payroll::query()
            ->where('status', '!=', 'draft')
            ->whereBetween('payroll_month', [
                $months->first()->toDateString(),
                $months->last()->copy()->endOfMonth()->toDateString(),
            ])
            ->groupBy('payroll_month')
            ->orderBy('payroll_month')
            ->selectRaw('payroll_month, SUM(total_salary_expense) salary_expense, SUM(paid_amount) paid, SUM(due_amount) due')
            ->get()
            ->keyBy(fn (Payroll $row): string => $row->payroll_month->format('Y-m'));

        return [
            'datasets' => [
                [
                    'label' => 'Salary expense',
                    'data' => $months->map(fn (Carbon $month): string => (string) ($payroll->get($month->format('Y-m'))?->salary_expense ?? '0')),
                    'backgroundColor' => '#8741b5',
                ],
                [
                    'label' => 'Paid',
                    'data' => $months->map(fn (Carbon $month): string => (string) ($payroll->get($month->format('Y-m'))?->paid ?? '0')),
                    'backgroundColor' => '#16a34a',
                ],
                [
                    'label' => 'Due',
                    'data' => $months->map(fn (Carbon $month): string => (string) ($payroll->get($month->format('Y-m'))?->due ?? '0')),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $months->map->format('M Y'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
