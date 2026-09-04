<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Analytics;

use App\Support\AnalyticsFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

final class OverheadCompositionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Where the overheads go';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected ?string $maxHeight = '300px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewProfit') ?? false;
    }

    protected function getData(): array
    {
        $filters = AnalyticsFilters::fromPage($this->pageFilters);

        $categories = DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expenses.expense_date', [$filters->from, $filters->to])
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->selectRaw('expense_categories.name, COALESCE(SUM(expenses.amount), 0) total')
            ->get();

        $salary = (string) DB::table('payrolls')
            ->where('status', '!=', 'draft')
            ->whereBetween('payroll_month', [$filters->from->copy()->startOfMonth(), $filters->to->copy()->endOfMonth()])
            ->sum('total_salary_expense');

        return [
            'datasets' => [[
                'label' => 'Spent',
                'data' => [...$categories->pluck('total')->all(), $salary],
                'backgroundColor' => ['#d33572', '#8741b5', '#435cea', '#f59e0b', '#16a34a', '#0891b2', '#e11d48', '#7c3aed', '#0284c7', '#ca8a04', '#475569'],
            ]],
            'labels' => [...$categories->pluck('name')->all(), 'Salary'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
