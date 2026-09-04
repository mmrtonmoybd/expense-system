<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Payroll;
use App\Models\Sale;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
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
        $salary = $this->salaryExpense($from, $to);

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
            ->selectRaw('platforms.name, SUM(sale_lines.usd_quantity) usd_sold, SUM(sale_lines.revenue) revenue, SUM(sale_lines.inventory_cost) inventory_cost, SUM(sale_lines.gross_profit) gross_profit, CASE WHEN SUM(sale_lines.usd_quantity) > 0 THEN SUM(sale_lines.revenue) / SUM(sale_lines.usd_quantity) ELSE 0 END average_selling_rate, COUNT(DISTINCT sales.id) sale_count, COUNT(DISTINCT sales.customer_id) customer_count');
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

    /**
     * Salary booked into the reported range.
     *
     * Payroll is recorded once per month, so a range covering part of a month carries that
     * month's share of days instead of the whole amount. The share is the difference between
     * two cumulative accruals, which makes adjacent ranges sum back to the exact month total
     * with no double counting and no lost cents.
     */
    private function salaryExpense(CarbonInterface $from, CarbonInterface $to): string
    {
        $rangeStart = Carbon::parse($from->toDateString())->startOfDay();
        $rangeEnd = Carbon::parse($to->toDateString())->startOfDay();

        if ($rangeEnd->lessThan($rangeStart)) {
            return Money::bdt('0');
        }

        $months = Payroll::query()
            ->where('status', '!=', 'draft')
            ->whereDate('payroll_month', '>=', $rangeStart->copy()->startOfMonth()->toDateString())
            ->whereDate('payroll_month', '<=', $rangeEnd->copy()->endOfMonth()->toDateString())
            ->groupBy('payroll_month')
            ->selectRaw('payroll_month, COALESCE(SUM(total_salary_expense), 0) total')
            ->get();

        $salary = Money::bdt('0');

        foreach ($months as $month) {
            $monthStart = Carbon::parse((string) $month->payroll_month)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $overlapStart = $rangeStart->greaterThan($monthStart) ? $rangeStart : $monthStart;
            $overlapEnd = $rangeEnd->lessThan($monthEnd) ? $rangeEnd : $monthEnd;

            if ($overlapEnd->lessThan($overlapStart)) {
                continue;
            }

            $total = Money::bdt((string) $month->total);
            $days = $monthStart->daysInMonth;
            $share = Money::subBdt(
                $this->accruedSalary($total, $overlapEnd->day, $days),
                $this->accruedSalary($total, $overlapStart->day - 1, $days),
            );

            $salary = Money::addBdt($salary, $share);
        }

        return $salary;
    }

    /** Salary accrued from the first of the month up to and including the given day. */
    private function accruedSalary(string $total, int $day, int $daysInMonth): string
    {
        if ($day <= 0) {
            return Money::bdt('0');
        }

        return $day >= $daysInMonth ? $total : Money::divideBdt(bcmul($total, (string) $day, 16), (string) $daysInMonth);
    }
}
