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
use App\Services\UsdCostingService;
use App\Services\UsdInventoryLayerService;
use App\Support\CostingMethod;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
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
        $salesMonth = Sale::query()->where('status', 'posted')->whereBetween('sale_date', [$month, $today]);
        $expenseToday = Money::bdt((string) Expense::query()->whereDate('expense_date', $today)->sum('amount'));
        $expenseMonth = Money::bdt((string) Expense::query()->whereBetween('expense_date', [$month, $today])->sum('amount'));
        $salaryMonth = Money::bdt((string) Payroll::query()->where('status', '!=', 'draft')->whereBetween('payroll_month', [$month, $today->copy()->endOfMonth()])->sum('total_salary_expense'));
        // Payroll is booked once per month on the first day, so a single day carries its share
        // of that month rather than the whole amount.
        $salaryToday = Money::divideBdt($salaryMonth, (string) $today->daysInMonth);
        $grossToday = Money::bdt((string) (clone $salesToday)->sum('profit_amount'));
        $grossMonth = Money::bdt((string) (clone $salesMonth)->sum('profit_amount'));
        $activeMethod = app(UsdCostingService::class)->activeMethod();
        $inventoryCostRate = $activeMethod === CostingMethod::Average
            ? (string) ($state?->weighted_average_cost ?? '0')
            : app(UsdInventoryLayerService::class)->bookCostRate();

        return [
            $this->makeStat('Available USD', number_format((float) ($state?->usd_balance ?? 0), 2), Heroicon::Banknotes, 'info', '#eff6ff', '#dbeafe', '#2563eb', 'rgba(37, 99, 235, 0.28)'),
            ...($canCosts ? [
                $this->makeStat(
                    $activeMethod === CostingMethod::Average ? 'Current inventory cost' : 'Inventory book cost',
                    '৳ '.number_format((float) $inventoryCostRate, 4),
                    Heroicon::Scale,
                    'gray',
                    '#f8fafc',
                    '#e2e8f0',
                    '#475569',
                    'rgba(71, 85, 105, 0.22)',
                    $activeMethod === CostingMethod::Average
                        ? 'Moving weighted average cost per USD'
                        : 'Remaining inventory book value per USD',
                ),
                $this->makeStat('Inventory value', '৳ '.number_format((float) ($state?->inventory_value_bdt ?? 0), 2), Heroicon::Wallet, 'primary', '#faf5ff', '#ede9fe', '#7c3aed', 'rgba(124, 58, 237, 0.24)'),
            ] : []),
            $this->makeStat('USD bought today', number_format((float) UsdPurchase::query()->where('status', 'posted')->whereDate('purchase_date', $today)->sum('usd_quantity'), 2), Heroicon::ArrowDownTray, 'warning', '#fff7ed', '#ffedd5', '#ea580c', 'rgba(234, 88, 12, 0.24)'),
            $this->makeStat('USD sold today', number_format((float) (clone $salesToday)->sum('total_usd'), 2), Heroicon::ArrowUpTray, 'success', '#ecfdf5', '#d1fae5', '#059669', 'rgba(5, 150, 105, 0.24)'),
            ...($canProfit ? [
                $this->makeStat('Revenue today', '৳ '.number_format((float) (clone $salesToday)->sum('sell_amount'), 2), Heroicon::PresentationChartLine, 'info', '#eff6ff', '#dbeafe', '#2563eb', 'rgba(37, 99, 235, 0.28)'),
                $this->makeStat("Today's realized profit", '৳ '.number_format((float) $grossToday, 2), Heroicon::Sparkles, 'success', '#ecfdf5', '#d1fae5', '#059669', 'rgba(5, 150, 105, 0.24)'),
                $this->makeStat('Expenses today', '৳ '.number_format((float) $expenseToday, 2), Heroicon::ReceiptPercent, 'danger', '#fef2f2', '#fee2e2', '#dc2626', 'rgba(220, 38, 38, 0.22)'),
                $this->makeStat('Net profit today', '৳ '.number_format((float) Money::subBdt(Money::subBdt($grossToday, $expenseToday), $salaryToday), 2), Heroicon::ChartBarSquare, 'primary', '#faf5ff', '#ede9fe', '#7c3aed', 'rgba(124, 58, 237, 0.24)', 'Includes ৳ '.number_format((float) $salaryToday, 2).' of daily salary accrual'),
                $this->makeStat('Revenue this month', '৳ '.number_format((float) (clone $salesMonth)->sum('sell_amount'), 2), Heroicon::PresentationChartBar, 'info', '#eff6ff', '#dbeafe', '#2563eb', 'rgba(37, 99, 235, 0.28)'),
                $this->makeStat('Gross profit this month', '৳ '.number_format((float) $grossMonth, 2), Heroicon::ArrowTrendingUp, 'success', '#ecfdf5', '#d1fae5', '#059669', 'rgba(5, 150, 105, 0.24)'),
                $this->makeStat('Expenses this month', '৳ '.number_format((float) $expenseMonth, 2), Heroicon::ArrowTrendingDown, 'danger', '#fff1f2', '#ffe4e6', '#e11d48', 'rgba(225, 29, 72, 0.22)'),
                $this->makeStat('Salary expense', '৳ '.number_format((float) $salaryMonth, 2), Heroicon::Users, 'warning', '#fff7ed', '#ffedd5', '#c2410c', 'rgba(194, 65, 12, 0.22)'),
                $this->makeStat('Net profit this month', '৳ '.number_format((float) Money::subBdt(Money::subBdt($grossMonth, $expenseMonth), $salaryMonth), 2), Heroicon::Calculator, 'primary', '#eef2ff', '#e0e7ff', '#4f46e5', 'rgba(79, 70, 229, 0.22)'),
            ] : []),
            $this->makeStat('Pending approvals', Sale::query()->where('status', 'pending')->count(), Heroicon::Clock, 'warning', '#fff7ed', '#ffedd5', '#ea580c', 'rgba(234, 88, 12, 0.24)'),
            $this->makeStat('Active customers', Customer::query()->where('is_active', true)->count(), Heroicon::UserGroup, 'info', '#eff6ff', '#dbeafe', '#2563eb', 'rgba(37, 99, 235, 0.28)'),
            // $this->makeStat('Active employees', Employee::query()->where('is_active', true)->count(), Heroicon::Identification, 'gray', '#f8fafc', '#e2e8f0', '#0f172a', 'rgba(15, 23, 42, 0.18)'),
        ];
    }

    private function makeStat(
        string $label,
        string|int $value,
        Heroicon $icon,
        string $color,
        string $gradientFrom,
        string $gradientTo,
        string $accentColor,
        string $glowColor,
        ?string $description = null,
    ): Stat {
        return Stat::make($label, (string) $value)
            ->icon($icon)
            ->color($color)
            ->when($description !== null, fn (Stat $stat): Stat => $stat->description($description))
            ->extraAttributes([
                'class' => 'finance-dashboard-stat-card',
                'style' => implode(' ', [
                    "--dashboard-stat-from: {$gradientFrom};",
                    "--dashboard-stat-to: {$gradientTo};",
                    "--dashboard-stat-accent: {$accentColor};",
                    "--dashboard-stat-glow: {$glowColor};",
                ]),
            ]);
    }
}
