<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class ReportDataBuilder
{
    public function __construct(private FinancialReportService $financialReports) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:array<string,int>|null}
     */
    public function build(array $filters, ?int $perPage = null, int $page = 1): array
    {
        $type = (string) ($filters['report'] ?? 'sales');
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth());
        $to = Carbon::parse($filters['to'] ?? now());

        return match ($type) {
            'purchases' => $this->simple(
                'USD Purchase Report',
                ['Number', 'Date', 'USD', 'Rate', 'BDT Total', 'Status'],
                $this->dated(DB::table('usd_purchases'), 'purchase_date', $from, $to)
                    ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
                    ->select('purchase_number', 'purchase_date', 'usd_quantity', 'purchase_rate', 'bdt_total', 'status'),
                $perPage,
                $page,
                dateColumns: [1],
            ),
            'inventory' => $this->simple(
                'Inventory Ledger',
                ['Date', 'Type', 'USD In', 'USD Out', 'Cost Rate', 'BDT In', 'BDT Out', 'USD Balance', 'Inventory Value', 'Average Cost'],
                $this->dated(DB::table('inventory_transactions'), 'occurred_at', $from, $to)
                    ->when($filters['status'] ?? null, fn (Builder $query, string $type): Builder => $query->where('transaction_type', $type))
                    ->select('occurred_at', 'transaction_type', 'usd_in', 'usd_out', 'cost_rate', 'bdt_value_in', 'bdt_value_out', 'resulting_usd_balance', 'resulting_inventory_value_bdt', 'resulting_weighted_average_cost'),
                $perPage,
                $page,
                dateTimeColumns: [0],
            ),
            'platforms' => $this->simple(
                'Platform Performance',
                ['Platform', 'USD Sold', 'Revenue', 'Inventory Cost', 'Gross Profit', 'Average Rate', 'Sale Count', 'Customer Count'],
                $this->financialReports->platformPerformanceQuery(
                    $from,
                    $to,
                    $this->integerFilter($filters, 'platform_id'),
                    $this->integerFilter($filters, 'customer_id'),
                ),
                $perPage,
                $page,
            ),
            'customers' => $this->simple(
                'Customer Performance',
                ['Customer', 'Phone', 'USD Sold', 'Revenue', 'Inventory Cost', 'Gross Profit', 'Sale Count'],
                $this->financialReports->customerPerformanceQuery(
                    $from,
                    $to,
                    $this->integerFilter($filters, 'customer_id'),
                    $this->integerFilter($filters, 'platform_id'),
                ),
                $perPage,
                $page,
            ),
            'expenses' => $this->simple(
                'Expense Report',
                ['Number', 'Date', 'Category', 'Amount', 'Description', 'Vendor'],
                $this->dated(
                    DB::table('expenses')->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id'),
                    'expense_date',
                    $from,
                    $to,
                )
                    ->when($filters['expense_category_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('expenses.expense_category_id', $id))
                    ->select('expense_number', 'expense_date', 'expense_categories.name', 'amount', 'expenses.description', 'vendor'),
                $perPage,
                $page,
                dateColumns: [1],
            ),
            'payroll' => $this->simple(
                'Payroll Report',
                ['Number', 'Month', 'Employee', 'Salary Expense', 'Paid', 'Due', 'Status'],
                $this->dated(
                    DB::table('payrolls')->join('employees', 'employees.id', '=', 'payrolls.employee_id'),
                    'payroll_month',
                    $from,
                    $to,
                )
                    ->when($filters['employee_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('payrolls.employee_id', $id))
                    ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('payrolls.status', $status))
                    ->select('payroll_number', 'payroll_month', 'employees.name', 'total_salary_expense', 'paid_amount', 'due_amount', 'payrolls.status'),
                $perPage,
                $page,
                dateColumns: [1],
            ),
            'pnl' => $this->pnl($from, $to),
            'audit' => $this->simple(
                'Financial Audit Log',
                ['Time', 'User', 'Action', 'Model', 'Record', 'Reason', 'IP'],
                $this->dated(
                    DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id'),
                    'occurred_at',
                    $from,
                    $to,
                )
                    ->when($filters['user_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('audit_logs.user_id', $id))
                    ->select('occurred_at', 'users.name', 'action', 'auditable_type', 'auditable_id', 'reason', 'ip_address'),
                $perPage,
                $page,
                dateTimeColumns: [0],
            ),
            default => $this->sales($filters, $from, $to, $perPage, $page),
        };
    }

    private function dated(Builder $query, string $column, Carbon $from, Carbon $to): Builder
    {
        return $query
            ->whereBetween($column, [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc($column);
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, int>  $dateColumns
     * @param  array<int, int>  $dateTimeColumns
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:array<string,int>|null}
     */
    private function simple(
        string $title,
        array $headings,
        Builder $query,
        ?int $perPage,
        int $page,
        array $dateColumns = [],
        array $dateTimeColumns = [],
    ): array {
        $pagination = null;

        if ($perPage !== null) {
            $paginator = $query->paginate(perPage: $perPage, page: $page);
            $records = $paginator->items();
            $pagination = $this->pagination($paginator);
        } else {
            $records = $query->get();
        }

        return [
            'title' => $title,
            'headings' => $headings,
            'rows' => $this->formatRows($records, $dateColumns, $dateTimeColumns),
            'totals' => [],
            'pagination' => $pagination,
        ];
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $records
     * @param  array<int, int>  $dateColumns
     * @param  array<int, int>  $dateTimeColumns
     * @return array<int, array<int, mixed>>
     */
    private function formatRows(iterable $records, array $dateColumns, array $dateTimeColumns): array
    {
        return collect($records)
            ->map(function (object|array $record) use ($dateColumns, $dateTimeColumns): array {
                $values = array_values((array) $record);

                foreach ($dateColumns as $index) {
                    if (filled($values[$index] ?? null)) {
                        $values[$index] = Carbon::parse($values[$index])->format((string) config('app.date_format'));
                    }
                }

                foreach ($dateTimeColumns as $index) {
                    if (filled($values[$index] ?? null)) {
                        $values[$index] = Carbon::parse($values[$index])->format((string) config('app.datetime_format'));
                    }
                }

                return $values;
            })
            ->all();
    }

    /** @return array{current_page:int,last_page:int,per_page:int,total:int,from:int,to:int} */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ];
    }

    /** @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:null} */
    private function pnl(Carbon $from, Carbon $to): array
    {
        $profitAndLoss = $this->financialReports->profitAndLoss($from, $to);

        return [
            'title' => 'Profit & Loss',
            'headings' => ['Revenue', 'Cost of USD Sold', 'Gross Profit', 'Office Expenses', 'Salary Expense', 'Net Profit'],
            'rows' => [[...array_values($profitAndLoss)]],
            'totals' => $profitAndLoss,
            'pagination' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:array<string,int>|null}
     */
    private function sales(array $filters, Carbon $from, Carbon $to, ?int $perPage, int $page): array
    {
        $query = $this->dated(
            DB::table('sales')->join('customers', 'customers.id', '=', 'sales.customer_id'),
            'sale_date',
            $from,
            $to,
        )
            ->when($filters['customer_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('sales.customer_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('sales.status', $status))
            ->when($filters['costing_method'] ?? null, fn (Builder $query, string $method): Builder => $query->where('sales.costing_method', $method))
            ->when($filters['platform_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->whereExists(
                fn (Builder $subquery): Builder => $subquery
                    ->selectRaw('1')
                    ->from('sale_lines')
                    ->whereColumn('sale_lines.sale_id', 'sales.id')
                    ->where('sale_lines.platform_id', $id),
            ))
            ->select('sale_number', 'sale_date', 'customers.name', 'customers.phone', 'total_usd', 'sell_amount', 'cost_amount', 'profit_amount')
            ->selectRaw("CASE sales.costing_method WHEN 'average' THEN 'Average' WHEN 'fifo' THEN 'FIFO' WHEN 'lifo' THEN 'LIFO' ELSE '-' END AS costing_method_label")
            ->addSelect('sales.status');

        return $this->simple(
            'USD Sale Report',
            ['Number', 'Date', 'Customer', 'Phone', 'USD', 'Revenue', 'Inventory Cost', 'Gross Profit', 'Costing Method', 'Status'],
            $query,
            $perPage,
            $page,
            dateColumns: [1],
        );
    }

    /** @param array<string, mixed> $filters */
    private function integerFilter(array $filters, string $key): ?int
    {
        return filled($filters[$key] ?? null) ? (int) $filters[$key] : null;
    }
}
