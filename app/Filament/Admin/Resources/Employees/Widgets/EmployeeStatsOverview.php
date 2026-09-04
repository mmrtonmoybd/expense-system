<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Widgets;

use App\Filament\Admin\Resources\Employees\Pages\ListEmployees;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

final class EmployeeStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListEmployees::class;
    }

    protected function getStats(): array
    {
        $canViewPayroll = auth()->user()?->can('ViewPayroll') ?? false;
        $counts = $this->summarise([
            'employees' => 'COUNT(*)',
            'active' => 'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)',
            'salary' => 'COALESCE(SUM(CASE WHEN is_active = 1 THEN current_salary ELSE 0 END), 0)',
        ]);

        $payroll = (array) (DB::table('payrolls')
            ->whereIn('employee_id', $this->summaryQuery()->select('employees.id'))
            ->where('status', '!=', 'draft')
            ->selectRaw('COALESCE(SUM(total_salary_expense), 0) expense, COALESCE(SUM(due_amount), 0) due')
            ->first() ?? []);

        return [
            $this->summaryStat('Employees in view', $this->quantity($counts['employees'], 0), Heroicon::Identification, 'gray', "{$counts['active']} active"),
            ...($canViewPayroll ? [
                $this->summaryStat('Monthly salary bill', $this->money($counts['salary']), Heroicon::Users, 'warning', 'Active employees only'),
                $this->summaryStat('Payroll booked', $this->money($payroll['expense'] ?? 0), Heroicon::ClipboardDocumentList, 'info'),
                $this->summaryStat('Salary outstanding', $this->money($payroll['due'] ?? 0), Heroicon::ExclamationTriangle, (float) ($payroll['due'] ?? 0) > 0 ? 'danger' : 'success'),
            ] : []),
        ];
    }
}
