<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Widgets;

use App\Filament\Admin\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;

final class ExpenseStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListExpenses::class;
    }

    protected function getStats(): array
    {
        $totals = $this->summarise([
            'entries' => 'COUNT(*)',
            'total' => 'COALESCE(SUM(expenses.amount), 0)',
            'average' => 'COALESCE(AVG(expenses.amount), 0)',
            'largest' => 'COALESCE(MAX(expenses.amount), 0)',
            'categories' => 'COUNT(DISTINCT expenses.expense_category_id)',
        ]);

        return [
            $this->summaryStat('Expenses in view', $this->quantity($totals['entries'], 0), Heroicon::ClipboardDocumentList, 'gray', "{$totals['categories']} categories"),
            $this->summaryStat('Total spent', $this->money($totals['total']), Heroicon::ReceiptPercent, 'danger'),
            $this->summaryStat('Average entry', $this->money($totals['average']), Heroicon::Calculator, 'warning'),
            $this->summaryStat('Largest entry', $this->money($totals['largest']), Heroicon::ArrowTrendingUp, 'primary'),
        ];
    }
}
