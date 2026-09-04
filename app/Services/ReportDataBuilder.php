<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ReportVisibility;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class ReportDataBuilder
{
    private const int MaxSummaryMonths = 60;

    public function __construct(private FinancialReportService $financialReports) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:array<string,int>|null}
     */
    public function build(array $filters, ?int $perPage = null, int $page = 1, ?ReportVisibility $visibility = null): array
    {
        $type = (string) ($filters['report'] ?? 'sales');
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth());
        $to = Carbon::parse($filters['to'] ?? now());
        $visibility ??= new ReportVisibility;

        abort_unless($visibility->allowsReport($type), 403);

        $report = match ($type) {
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
            'monthly_summary' => $this->monthlySummary($from, $to),
            'sale_lines' => $this->saleLines($filters, $from, $to, $perPage, $page),
            'invoices' => $this->simple(
                'Invoice Register',
                ['Number', 'Date', 'Sale', 'Customer', 'Phone', 'USD', 'Total', 'Status'],
                $this->dated(
                    DB::table('invoices')
                        ->join('sales', 'sales.id', '=', 'invoices.sale_id')
                        ->join('customers', 'customers.id', '=', 'sales.customer_id'),
                    'invoice_date',
                    $from,
                    $to,
                )
                    ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('invoices.status', $status))
                    ->when($this->integerFilter($filters, 'customer_id'), fn (Builder $query, int $id): Builder => $query->where('sales.customer_id', $id))
                    ->select('invoices.invoice_number', 'invoices.invoice_date', 'sales.sale_number', 'customers.name', 'customers.phone', 'sales.total_usd', 'sales.sell_amount', 'invoices.status'),
                $perPage,
                $page,
                dateColumns: [1],
            ),
            'inventory_valuation' => $this->simple(
                'Inventory Valuation',
                ['Acquired', 'Origin', 'Purchase', 'Original USD', 'Remaining USD', 'Rate', 'Remaining value', 'Status'],
                DB::table('inventory_layers')
                    ->leftJoin('usd_purchases', 'usd_purchases.id', '=', 'inventory_layers.usd_purchase_id')
                    ->whereBetween('inventory_layers.acquired_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orderBy('inventory_layers.acquired_at')
                    ->orderBy('inventory_layers.id')
                    ->select(
                        'inventory_layers.acquired_at',
                        'inventory_layers.origin_type',
                        'usd_purchases.purchase_number',
                        'inventory_layers.original_quantity',
                        'inventory_layers.remaining_quantity',
                        'inventory_layers.purchase_rate',
                        'inventory_layers.remaining_cost_bdt',
                    )
                    ->selectRaw("CASE WHEN inventory_layers.is_closed = 1 THEN 'Closed' ELSE 'Open' END as layer_status"),
                $perPage,
                $page,
                dateTimeColumns: [0],
            ),
            'rate_history' => $this->simple(
                'Customer Rate History',
                ['Changed at', 'Customer', 'Platform', 'Old rate', 'New rate', 'Changed by', 'Reason'],
                $this->dated(
                    DB::table('customer_platform_rate_histories')
                        ->join('customers', 'customers.id', '=', 'customer_platform_rate_histories.customer_id')
                        ->join('platforms', 'platforms.id', '=', 'customer_platform_rate_histories.platform_id')
                        ->leftJoin('users', 'users.id', '=', 'customer_platform_rate_histories.changed_by'),
                    'effective_at',
                    $from,
                    $to,
                )
                    ->when($this->integerFilter($filters, 'customer_id'), fn (Builder $query, int $id): Builder => $query->where('customer_platform_rate_histories.customer_id', $id))
                    ->when($this->integerFilter($filters, 'platform_id'), fn (Builder $query, int $id): Builder => $query->where('customer_platform_rate_histories.platform_id', $id))
                    ->select('effective_at', 'customers.name', 'platforms.name as platform_name', 'old_rate', 'new_rate', 'users.name as changed_by_name', 'reason'),
                $perPage,
                $page,
                dateTimeColumns: [0],
            ),
            'customer_statement' => $this->customerStatement($filters, $from, $to),
            'employee_statement' => $this->employeeStatement($filters, $from, $to),
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

        return $this->hideRestrictedColumns($report, $type, $visibility);
    }

    /**
     * Strip inventory cost and profit columns the requester is not allowed to see, matching
     * the column visibility the resource tables already apply.
     *
     * @param  array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:array<string,int>|null}  $report
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:array<string,int>|null}
     */
    private function hideRestrictedColumns(array $report, string $type, ReportVisibility $visibility): array
    {
        if ($visibility->costs && $visibility->profit) {
            return $report;
        }

        /** @var array{costs:array<int,int>,profit:array<int,int>}|null $columns */
        $columns = match ($type) {
            'platforms' => ['costs' => [3], 'profit' => [4]],
            'customers' => ['costs' => [4], 'profit' => [5]],
            'sale_lines' => ['costs' => [7], 'profit' => [8]],
            'purchases', 'inventory', 'inventory_valuation', 'invoices', 'expenses', 'payroll',
            'pnl', 'monthly_summary', 'audit', 'rate_history', 'customer_statement', 'employee_statement' => null,
            default => ['costs' => [6], 'profit' => [7]],
        };

        if ($columns === null) {
            return $report;
        }

        $drop = array_flip([
            ...($visibility->costs ? [] : $columns['costs']),
            ...($visibility->profit ? [] : $columns['profit']),
        ]);

        if ($drop === []) {
            return $report;
        }

        $keep = fn (array $values): array => array_values(array_diff_key($values, $drop));

        $report['headings'] = $keep($report['headings']);
        $report['rows'] = array_map($keep, $report['rows']);

        return $report;
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

    /**
     * Month-by-month revenue, cost, profit, overheads and net result.
     *
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:null}
     */
    private function monthlySummary(Carbon $from, Carbon $to): array
    {
        $month = $from->copy()->startOfMonth();
        $last = $to->copy()->startOfMonth();
        $rows = [];
        $totals = ['revenue' => '0.00', 'cost_of_usd_sold' => '0.00', 'gross_profit' => '0.00', 'office_expenses' => '0.00', 'salary_expense' => '0.00', 'net_profit' => '0.00'];

        while ($month->lessThanOrEqualTo($last) && count($rows) < self::MaxSummaryMonths) {
            $windowStart = $month->copy()->startOfMonth()->max($from->copy()->startOfDay());
            $windowEnd = $month->copy()->endOfMonth()->min($to->copy()->startOfDay());
            $report = $this->financialReports->profitAndLoss($windowStart, $windowEnd);

            $rows[] = [
                $month->format('M Y'),
                $report['revenue'],
                $report['cost_of_usd_sold'],
                $report['gross_profit'],
                $report['office_expenses'],
                $report['salary_expense'],
                $report['net_profit'],
            ];

            foreach ($totals as $key => $value) {
                $totals[$key] = bcadd($value, $report[$key], 2);
            }

            $month->addMonthNoOverflow();
        }

        return [
            'title' => 'Monthly Business Summary',
            'headings' => ['Month', 'Revenue', 'Cost of USD Sold', 'Gross Profit', 'Office Expenses', 'Salary Expense', 'Net Profit'],
            'rows' => $rows,
            'totals' => $totals,
            'pagination' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:array<string,int>|null}
     */
    private function saleLines(array $filters, Carbon $from, Carbon $to, ?int $perPage, int $page): array
    {
        $query = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->join('platforms', 'platforms.id', '=', 'sale_lines.platform_id')
            ->whereBetween('sales.sale_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('sales.status', $status), fn (Builder $query): Builder => $query->where('sales.status', 'posted'))
            ->when($this->integerFilter($filters, 'customer_id'), fn (Builder $query, int $id): Builder => $query->where('sales.customer_id', $id))
            ->when($this->integerFilter($filters, 'platform_id'), fn (Builder $query, int $id): Builder => $query->where('sale_lines.platform_id', $id))
            ->orderByDesc('sales.sale_date')
            ->orderByDesc('sales.id')
            ->select(
                'sales.sale_number',
                'sales.sale_date',
                'customers.name',
                'platforms.name as platform_name',
                'sale_lines.usd_quantity',
                'sale_lines.selling_rate',
                'sale_lines.revenue',
                'sale_lines.inventory_cost',
                'sale_lines.gross_profit',
            )
            ->selectRaw("CASE WHEN sale_lines.is_rate_overridden = 1 THEN 'Yes' ELSE 'No' END as rate_overridden");

        return $this->simple(
            'Sales by Platform Line',
            ['Sale', 'Date', 'Customer', 'Platform', 'USD', 'Rate', 'Revenue', 'Inventory Cost', 'Gross Profit', 'Rate Overridden'],
            $query,
            $perPage,
            $page,
            dateColumns: [1],
        );
    }

    /**
     * Every posted sale for one customer in date order, with a running balance.
     *
     * @param  array<string, mixed>  $filters
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:null}
     */
    private function customerStatement(array $filters, Carbon $from, Carbon $to): array
    {
        $customerId = $this->integerFilter($filters, 'customer_id');
        $customer = $customerId === null ? null : DB::table('customers')->find($customerId);

        if ($customer === null) {
            return $this->emptyStatement('Customer Statement', ['Date', 'Sale', 'Invoice', 'Platforms', 'USD', 'Rate', 'Amount', 'Running total'], 'Select a customer to build this statement.');
        }

        $sales = DB::table('sales')
            ->leftJoin('invoices', 'invoices.sale_id', '=', 'sales.id')
            ->where('sales.customer_id', $customerId)
            ->where('sales.status', 'posted')
            ->whereBetween('sales.sale_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('sales.sale_date')
            ->orderBy('sales.id')
            ->select('sales.id', 'sales.sale_number', 'sales.sale_date', 'sales.total_usd', 'sales.sell_rate', 'sales.sell_amount', 'invoices.invoice_number')
            ->get();

        $platforms = DB::table('sale_lines')
            ->join('platforms', 'platforms.id', '=', 'sale_lines.platform_id')
            ->whereIn('sale_lines.sale_id', $sales->pluck('id'))
            ->select('sale_lines.sale_id', 'platforms.name')
            ->get()
            ->groupBy('sale_id')
            ->map(fn ($lines): string => $lines->pluck('name')->implode(', '));

        $runningUsd = '0';
        $runningAmount = '0';
        $rows = [];

        foreach ($sales as $sale) {
            $runningUsd = bcadd($runningUsd, (string) $sale->total_usd, 8);
            $runningAmount = bcadd($runningAmount, (string) $sale->sell_amount, 2);

            $rows[] = [
                Carbon::parse((string) $sale->sale_date)->format((string) config('app.date_format')),
                $sale->sale_number,
                $sale->invoice_number ?? '-',
                $platforms[$sale->id] ?? '-',
                $sale->total_usd,
                $sale->sell_rate,
                $sale->sell_amount,
                $runningAmount,
            ];
        }

        return [
            'title' => "Customer Statement — {$customer->name}",
            'headings' => ['Date', 'Sale', 'Invoice', 'Platforms', 'USD', 'Rate', 'Amount', 'Running total'],
            'rows' => $rows,
            'totals' => [
                'customer' => $customer->name,
                'phone' => $customer->phone,
                'sales' => (string) $sales->count(),
                'total_usd' => bcadd($runningUsd, '0', 8),
                'total_amount' => bcadd($runningAmount, '0', 2),
            ],
            'pagination' => null,
        ];
    }

    /**
     * Salary charged and paid for one employee in date order, with the outstanding balance.
     *
     * @param  array<string, mixed>  $filters
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:null}
     */
    private function employeeStatement(array $filters, Carbon $from, Carbon $to): array
    {
        $headings = ['Date', 'Entry', 'Reference', 'Salary charged', 'Paid', 'Balance'];
        $employeeId = $this->integerFilter($filters, 'employee_id');
        $employee = $employeeId === null ? null : DB::table('employees')->find($employeeId);

        if ($employee === null) {
            return $this->emptyStatement('Employee Salary Statement', $headings, 'Select an employee to build this statement.');
        }

        $charges = DB::table('payrolls')
            ->where('employee_id', $employeeId)
            ->where('status', '!=', 'draft')
            ->whereBetween('payroll_month', [$from->copy()->startOfMonth(), $to->copy()->endOfMonth()])
            ->orderBy('payroll_month')
            ->get(['payroll_month as entry_date', 'payroll_number as reference', 'total_salary_expense as amount'])
            ->map(fn (object $row): array => ['date' => (string) $row->entry_date, 'type' => 'Payroll', 'reference' => (string) $row->reference, 'charged' => (string) $row->amount, 'paid' => '0.00']);

        $payments = DB::table('payroll_payments')
            ->join('payrolls', 'payrolls.id', '=', 'payroll_payments.payroll_id')
            ->where('payrolls.employee_id', $employeeId)
            ->whereBetween('payroll_payments.payment_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('payroll_payments.payment_date')
            ->get(['payroll_payments.payment_date as entry_date', 'payrolls.payroll_number as reference', 'payroll_payments.amount', 'payroll_payments.method'])
            ->map(fn (object $row): array => ['date' => (string) $row->entry_date, 'type' => filled($row->method) ? "Payment ({$row->method})" : 'Payment', 'reference' => (string) $row->reference, 'charged' => '0.00', 'paid' => (string) $row->amount]);

        $entries = $charges->concat($payments)->sortBy('date')->values();
        $balance = '0.00';
        $totalCharged = '0.00';
        $totalPaid = '0.00';
        $rows = [];

        foreach ($entries as $entry) {
            $balance = bcsub(bcadd($balance, $entry['charged'], 2), $entry['paid'], 2);
            $totalCharged = bcadd($totalCharged, $entry['charged'], 2);
            $totalPaid = bcadd($totalPaid, $entry['paid'], 2);

            $rows[] = [
                Carbon::parse($entry['date'])->format((string) config('app.date_format')),
                $entry['type'],
                $entry['reference'],
                $entry['charged'],
                $entry['paid'],
                $balance,
            ];
        }

        return [
            'title' => "Employee Salary Statement — {$employee->name}",
            'headings' => $headings,
            'rows' => $rows,
            'totals' => [
                'employee' => $employee->name,
                'employee_code' => $employee->employee_code,
                'salary_charged' => $totalCharged,
                'salary_paid' => $totalPaid,
                'outstanding' => $balance,
            ],
            'pagination' => null,
        ];
    }

    /**
     * @param  array<int, string>  $headings
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,mixed>>,totals:array<string,mixed>,pagination:null}
     */
    private function emptyStatement(string $title, array $headings, string $message): array
    {
        return ['title' => $title, 'headings' => $headings, 'rows' => [], 'totals' => ['message' => $message], 'pagination' => null];
    }

    /** @param array<string, mixed> $filters */
    private function integerFilter(array $filters, string $key): ?int
    {
        return filled($filters[$key] ?? null) ? (int) $filters[$key] : null;
    }
}
