<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Expense;
use Filament\Widgets\ChartWidget;

final class ExpenseBreakdownChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Expenses by category';

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
        $rows = Expense::query()->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->whereBetween('expense_date', [now()->startOfMonth(), now()])->groupBy('expense_categories.id', 'expense_categories.name')->selectRaw('expense_categories.name, SUM(expenses.amount) total')->orderByDesc('total')->get();

        return ['datasets' => [['label' => 'Expenses', 'data' => $rows->pluck('total'), 'backgroundColor' => ['#d33572', '#8741b5', '#435cea', '#f59e0b', '#16a34a', '#0891b2']]], 'labels' => $rows->pluck('name')];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
