<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Analytics;

use App\Support\AnalyticsFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PayrollCoverageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Payroll charged, paid and outstanding';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewPayroll') ?? false;
    }

    protected function getData(): array
    {
        $filters = AnalyticsFilters::fromPage($this->pageFilters);

        $rows = DB::table('payrolls')
            ->where('status', '!=', 'draft')
            ->whereBetween('payroll_month', [$filters->from->copy()->startOfMonth(), $filters->to->copy()->endOfMonth()])
            ->groupBy('payroll_month')
            ->orderBy('payroll_month')
            ->limit(60)
            ->selectRaw('payroll_month, COALESCE(SUM(total_salary_expense), 0) expense, COALESCE(SUM(paid_amount), 0) paid, COALESCE(SUM(due_amount), 0) due')
            ->get();

        return [
            'datasets' => [
                ['label' => 'Charged', 'data' => $rows->pluck('expense'), 'backgroundColor' => '#8741b5'],
                ['label' => 'Paid', 'data' => $rows->pluck('paid'), 'backgroundColor' => '#16a34a'],
                ['label' => 'Outstanding', 'data' => $rows->pluck('due'), 'backgroundColor' => '#f59e0b'],
            ],
            'labels' => $rows->map(fn (object $row): string => Carbon::parse((string) $row->payroll_month)->format('M Y')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
