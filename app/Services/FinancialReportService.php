<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Payroll;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class FinancialReportService
{
    /** @return array<string, string> */
    public function profitAndLoss(CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = Sale::query()->where('status', 'posted')->whereDate('sale_date', '>=', $from->toDateString())->whereDate('sale_date', '<=', $to->toDateString())->selectRaw('COALESCE(SUM(sell_amount), 0) revenue, COALESCE(SUM(cost_amount), 0) cost, COALESCE(SUM(profit_amount), 0) gross_profit')->first();
        $revenue = bcadd((string) $sales->revenue, '0', 2);
        $cost = bcadd((string) $sales->cost, '0', 2);
        $grossProfit = bcadd((string) $sales->gross_profit, '0', 2);
        $expenses = bcadd((string) Expense::query()->whereDate('expense_date', '>=', $from->toDateString())->whereDate('expense_date', '<=', $to->toDateString())->sum('amount'), '0', 2);
        $salary = bcadd((string) Payroll::query()->where('status', '!=', 'draft')->whereDate('payroll_month', '>=', $from->copy()->startOfMonth()->toDateString())->whereDate('payroll_month', '<=', $to->copy()->endOfMonth()->toDateString())->sum('total_salary_expense'), '0', 2);

        return ['revenue' => $revenue, 'cost_of_usd_sold' => $cost, 'gross_profit' => $grossProfit, 'office_expenses' => $expenses, 'salary_expense' => $salary, 'net_profit' => bcsub(bcsub($grossProfit, $expenses, 2), $salary, 2)];
    }

    public function platformPerformanceQuery(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $platformId = null,
        ?int $customerId = null,
    ): Builder {
        return DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('platforms', 'platforms.id', '=', 'sale_lines.platform_id')
            ->where('sales.status', 'posted')
            ->where('sales.sale_date', '>=', $from->toDateString())
            ->where('sales.sale_date', '<', $to->copy()->addDay()->toDateString())
            ->when($platformId, fn (Builder $query): Builder => $query->where('sale_lines.platform_id', $platformId))
            ->when($customerId, fn (Builder $query): Builder => $query->where('sales.customer_id', $customerId))
            ->groupBy('platforms.id', 'platforms.name')
            ->orderByDesc('revenue')
            ->selectRaw('platforms.name, SUM(sale_lines.usd_quantity) usd_sold, SUM(sale_lines.revenue) revenue, SUM(sale_lines.inventory_cost) inventory_cost, SUM(sale_lines.gross_profit) gross_profit, AVG(sale_lines.selling_rate) average_selling_rate, COUNT(DISTINCT sales.id) sale_count, COUNT(DISTINCT sales.customer_id) customer_count');
    }

    public function customerPerformanceQuery(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $customerId = null,
        ?int $platformId = null,
    ): Builder {
        return DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.status', 'posted')
            ->where('sales.sale_date', '>=', $from->toDateString())
            ->where('sales.sale_date', '<', $to->copy()->addDay()->toDateString())
            ->when($customerId, fn (Builder $query): Builder => $query->where('sales.customer_id', $customerId))
            ->when($platformId, fn (Builder $query): Builder => $query->where('sale_lines.platform_id', $platformId))
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->orderByDesc('revenue')
            ->selectRaw('customers.name, customers.phone, SUM(sale_lines.usd_quantity) usd_sold, SUM(sale_lines.revenue) revenue, SUM(sale_lines.inventory_cost) inventory_cost, SUM(sale_lines.gross_profit) gross_profit, COUNT(DISTINCT sales.id) sale_count');
    }
}
