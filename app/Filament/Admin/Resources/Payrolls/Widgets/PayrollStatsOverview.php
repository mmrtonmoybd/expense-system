<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Widgets;

use App\Filament\Admin\Resources\Payrolls\Pages\ListPayrolls;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;

final class PayrollStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListPayrolls::class;
    }

    protected function getStats(): array
    {
        $totals = $this->summarise([
            'runs' => 'COUNT(*)',
            'employees' => 'COUNT(DISTINCT employee_id)',
            'expense' => 'COALESCE(SUM(total_salary_expense), 0)',
            'paid' => 'COALESCE(SUM(paid_amount), 0)',
            'due' => 'COALESCE(SUM(due_amount), 0)',
            'unpaid' => "SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END)",
        ]);

        $settled = (float) $totals['expense'] > 0
            ? number_format((float) $totals['paid'] / (float) $totals['expense'] * 100, 1)
            : '0.0';

        return [
            $this->summaryStat('Payroll runs in view', $this->quantity($totals['runs'], 0), Heroicon::ClipboardDocumentList, 'gray', "{$totals['employees']} employees · {$totals['unpaid']} untouched"),
            $this->summaryStat('Salary expense', $this->money($totals['expense']), Heroicon::Users, 'warning'),
            $this->summaryStat('Paid', $this->money($totals['paid']), Heroicon::CheckCircle, 'success', "{$settled}% settled"),
            $this->summaryStat('Outstanding', $this->money($totals['due']), Heroicon::ExclamationTriangle, (float) $totals['due'] > 0 ? 'danger' : 'success'),
        ];
    }
}
