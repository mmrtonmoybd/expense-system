<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Widgets;

use App\Filament\Admin\Resources\Payrolls\Pages\ListPayrolls;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;

final class PayrollTrendChart extends ChartWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected ?string $heading = 'Payroll in view, by month';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    protected function getTablePage(): string
    {
        return ListPayrolls::class;
    }

    protected function getData(): array
    {
        $rows = $this->summaryQuery()
            ->selectRaw('payroll_month, COALESCE(SUM(total_salary_expense), 0) expense, COALESCE(SUM(paid_amount), 0) paid, COALESCE(SUM(due_amount), 0) due')
            ->groupBy('payroll_month')
            ->orderBy('payroll_month')
            ->limit(36)
            ->get();

        return [
            'datasets' => [
                ['label' => 'Salary expense', 'data' => $rows->pluck('expense'), 'backgroundColor' => '#8741b5'],
                ['label' => 'Paid', 'data' => $rows->pluck('paid'), 'backgroundColor' => '#16a34a'],
                ['label' => 'Due', 'data' => $rows->pluck('due'), 'backgroundColor' => '#f59e0b'],
            ],
            'labels' => $rows->map(fn (object $row): string => Carbon::parse((string) $row->payroll_month)->format('M Y')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
