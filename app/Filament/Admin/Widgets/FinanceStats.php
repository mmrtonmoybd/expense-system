<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\InventoryState;
use App\Models\Payroll;
use App\Models\Sale;
use App\Models\UsdPurchase;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FinanceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $state = InventoryState::query()->first();
        $today = today();
        $month = now()->startOfMonth();
        $canProfit = auth()->user()->can('ViewProfit');
        $canCosts = auth()->user()->can('ViewInternalCosts');
        $salesToday = Sale::query()->where('status', 'posted')->whereDate('sale_date', $today);
        $salesMonth = Sale::query()->where('status', 'posted')->whereBetween('sale_date', [$month, today()]);
        $expenseToday = (string) Expense::query()->whereDate('expense_date', $today)->sum('amount');
        $expenseMonth = (string) Expense::query()->whereBetween('expense_date', [$month, today()])->sum('amount');
        $salaryMonth = (string) Payroll::query()->where('status', '!=', 'draft')->whereBetween('payroll_month', [$month, today()->endOfMonth()])->sum('total_salary_expense');
        $salaryToday = (string) Payroll::query()->where('status', '!=', 'draft')->whereDate('payroll_month', $today)->sum('total_salary_expense');
        $grossToday = (string) (clone $salesToday)->sum('total_gross_profit');
        $grossMonth = (string) (clone $salesMonth)->sum('total_gross_profit');

        return [Stat::make('Available USD', number_format((float) ($state?->usd_balance ?? 0), 2)), ...($canCosts ? [Stat::make('Inventory value', '৳ '.number_format((float) ($state?->inventory_value_bdt ?? 0), 2)), Stat::make('Weighted average', '৳ '.number_format((float) ($state?->weighted_average_cost ?? 0), 4))] : []), Stat::make('USD bought today', number_format((float) UsdPurchase::query()->where('status', 'posted')->whereDate('purchase_date', $today)->sum('usd_quantity'), 2)), Stat::make('USD sold today', number_format((float) (clone $salesToday)->sum('total_usd'), 2)), ...($canProfit ? [Stat::make('Revenue today', '৳ '.number_format((float) (clone $salesToday)->sum('total_bdt_revenue'), 2)), Stat::make('Gross profit today', '৳ '.number_format((float) $grossToday, 2)), Stat::make('Expenses today', '৳ '.number_format((float) $expenseToday, 2)), Stat::make('Net profit today', '৳ '.number_format((float) bcsub(bcsub($grossToday, $expenseToday, 2), $salaryToday, 2), 2)), Stat::make('Revenue this month', '৳ '.number_format((float) (clone $salesMonth)->sum('total_bdt_revenue'), 2)), Stat::make('Gross profit this month', '৳ '.number_format((float) $grossMonth, 2)), Stat::make('Expenses this month', '৳ '.number_format((float) $expenseMonth, 2)), Stat::make('Salary expense', '৳ '.number_format((float) $salaryMonth, 2)), Stat::make('Net profit this month', '৳ '.number_format((float) bcsub(bcsub($grossMonth, $expenseMonth, 2), $salaryMonth, 2), 2))] : []), Stat::make('Pending approvals', Sale::query()->where('status', 'pending')->count()), Stat::make('Active customers', Customer::query()->where('is_active', true)->count()), Stat::make('Active employees', Employee::query()->where('is_active', true)->count())];
    }
}
