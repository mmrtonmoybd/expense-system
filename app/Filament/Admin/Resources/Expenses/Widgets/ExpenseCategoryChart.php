<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Widgets;

use App\Filament\Admin\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

final class ExpenseCategoryChart extends ChartWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected ?string $heading = 'Expenses in view, by category';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    protected function getTablePage(): string
    {
        return ListExpenses::class;
    }

    protected function getData(): array
    {
        $rows = $this->summaryQuery()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.name, COALESCE(SUM(expenses.amount), 0) total')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Spent',
                'data' => $rows->pluck('total'),
                'backgroundColor' => ['#d33572', '#8741b5', '#435cea', '#f59e0b', '#16a34a', '#0891b2'],
            ]],
            'labels' => $rows->pluck('name'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
