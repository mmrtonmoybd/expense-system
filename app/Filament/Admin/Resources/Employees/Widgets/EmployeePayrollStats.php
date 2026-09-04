<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Widgets;

use App\Models\Employee;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EmployeePayrollStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewPayroll') ?? false;
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $employee = $this->record;

        if (! $employee instanceof Employee) {
            return [];
        }

        $totals = (array) (DB::table('payrolls')
            ->where('employee_id', $employee->getKey())
            ->where('status', '!=', 'draft')
            ->selectRaw('COUNT(*) runs, COALESCE(SUM(total_salary_expense), 0) charged, COALESCE(SUM(paid_amount), 0) paid, COALESCE(SUM(due_amount), 0) due, MAX(payment_date) last_payment')
            ->first() ?? []);

        $lastPayment = filled($totals['last_payment'] ?? null)
            ? Carbon::parse((string) $totals['last_payment'])->format((string) config('app.date_format'))
            : 'Never';

        return [
            Stat::make('Current salary', '৳ '.number_format((float) $employee->current_salary, 2))
                ->description(number_format((float) ($totals['runs'] ?? 0)).' payroll runs booked')
                ->icon(Heroicon::Identification)
                ->color('gray'),
            Stat::make('Salary charged', '৳ '.number_format((float) ($totals['charged'] ?? 0), 2))
                ->icon(Heroicon::ClipboardDocumentList)
                ->color('warning'),
            Stat::make('Salary paid', '৳ '.number_format((float) ($totals['paid'] ?? 0), 2))
                ->description("Last payment {$lastPayment}")
                ->icon(Heroicon::CheckCircle)
                ->color('success'),
            Stat::make('Outstanding', '৳ '.number_format((float) ($totals['due'] ?? 0), 2))
                ->icon(Heroicon::ExclamationTriangle)
                ->color((float) ($totals['due'] ?? 0) > 0 ? 'danger' : 'success'),
        ];
    }
}
