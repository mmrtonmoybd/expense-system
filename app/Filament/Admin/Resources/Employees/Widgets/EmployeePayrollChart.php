<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Widgets;

use App\Models\Employee;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EmployeePayrollChart extends ChartWidget
{
    public ?Model $record = null;

    protected ?string $heading = 'Salary charged and paid by month';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewPayroll') ?? false;
    }

    protected function getData(): array
    {
        $employee = $this->record;

        if (! $employee instanceof Employee) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = DB::table('payrolls')
            ->where('employee_id', $employee->getKey())
            ->where('status', '!=', 'draft')
            ->orderBy('payroll_month')
            ->limit(24)
            ->get(['payroll_month', 'total_salary_expense', 'paid_amount', 'due_amount']);

        return [
            'datasets' => [
                ['label' => 'Charged', 'data' => $rows->pluck('total_salary_expense'), 'backgroundColor' => '#8741b5'],
                ['label' => 'Paid', 'data' => $rows->pluck('paid_amount'), 'backgroundColor' => '#16a34a'],
                ['label' => 'Outstanding', 'data' => $rows->pluck('due_amount'), 'backgroundColor' => '#f59e0b'],
            ],
            'labels' => $rows->map(fn (object $row): string => Carbon::parse((string) $row->payroll_month)->format('M Y')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
