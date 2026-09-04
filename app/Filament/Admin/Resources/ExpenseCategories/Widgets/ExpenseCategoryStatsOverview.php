<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseCategories\Widgets;

use App\Filament\Admin\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

final class ExpenseCategoryStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListExpenseCategories::class;
    }

    protected function getStats(): array
    {
        $counts = $this->summarise([
            'categories' => 'COUNT(*)',
            'active' => 'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)',
        ]);

        $expenses = (array) (DB::table('expenses')
            ->whereIn('expense_category_id', $this->summaryQuery()->select('expense_categories.id'))
            ->selectRaw('COUNT(*) entries, COALESCE(SUM(amount), 0) total, COALESCE(AVG(amount), 0) average')
            ->first() ?? []);

        return [
            $this->summaryStat('Categories in view', $this->quantity($counts['categories'], 0), Heroicon::Tag, 'gray', "{$counts['active']} active"),
            $this->summaryStat('Expense entries', $this->quantity($expenses['entries'] ?? 0, 0), Heroicon::ClipboardDocumentList, 'info'),
            $this->summaryStat('Total spent', $this->money($expenses['total'] ?? 0), Heroicon::ReceiptPercent, 'danger'),
            $this->summaryStat('Average entry', $this->money($expenses['average'] ?? 0), Heroicon::Calculator, 'warning'),
        ];
    }
}
