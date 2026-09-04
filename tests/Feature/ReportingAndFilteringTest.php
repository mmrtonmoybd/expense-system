<?php

declare(strict_types=1);

use App\Actions\PostSaleAction;
use App\Actions\PostUsdPurchaseAction;
use App\Actions\RecordPayrollPaymentAction;
use App\Filament\Admin\Pages\BusinessAnalytics;
use App\Filament\Admin\Pages\Reports;
use App\Filament\Admin\Pages\Statements;
use App\Filament\Admin\Resources\Sales\Pages\ListSales;
use App\Filament\Admin\Resources\Sales\Widgets\SaleStatsOverview;
use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payroll;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Services\ReportDataBuilder;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

function reportingOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');

    return $user;
}

function reportingSale(User $user, Customer $customer, Platform $platform, string $quantity, string $rate, ?string $date = null): Sale
{
    $sale = Sale::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $user->id,
        'sale_date' => $date ?? today()->toDateString(),
    ]);

    CustomerPlatform::query()->firstOrCreate(
        ['customer_id' => $customer->id, 'platform_id' => $platform->id],
        ['current_selling_rate' => $rate],
    );

    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => $quantity,
        'selling_rate' => $rate,
        'weighted_cost_rate' => '0',
        'revenue' => '0',
        'inventory_cost' => '0',
        'gross_profit' => '0',
    ]);

    return app(PostSaleAction::class)->execute($sale, $user);
}

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
});

test('the sales summary widget follows the table filters', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '10000', 'purchase_rate' => '100']), $user);

    $alpha = Customer::factory()->create(['name' => 'Alpha Trading']);
    $beta = Customer::factory()->create(['name' => 'Beta Traders']);
    $platform = Platform::factory()->create();

    reportingSale($user, $alpha, $platform, '100', '120');
    reportingSale($user, $beta, $platform, '400', '130');

    Livewire::test(SaleStatsOverview::class, ['pageFilters' => null])
        ->assertSee('500.00')
        ->assertSee('৳ 64,000.00');

    Livewire::test(SaleStatsOverview::class, [
        'tableFilters' => ['customer_id' => ['values' => [$alpha->id]]],
    ])
        ->assertSee('100.00')
        ->assertSee('৳ 12,000.00')
        ->assertDontSee('৳ 64,000.00');
});

test('the sales table filters by customer, status and a date range', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '10000', 'purchase_rate' => '100']), $user);

    $alpha = Customer::factory()->create(['name' => 'Alpha Trading']);
    $beta = Customer::factory()->create(['name' => 'Beta Traders']);
    $platform = Platform::factory()->create();

    $today = reportingSale($user, $alpha, $platform, '100', '120');
    $old = reportingSale($user, $beta, $platform, '100', '120', today()->subMonths(2)->toDateString());

    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$today, $old])
        ->filterTable('customer_id', [$alpha->id])
        ->assertCanSeeTableRecords([$today])
        ->assertCanNotSeeTableRecords([$old])
        ->resetTableFilters()
        ->filterTable('sale_date', ['sale_date_from' => today()->subWeek()->toDateString()])
        ->assertCanSeeTableRecords([$today])
        ->assertCanNotSeeTableRecords([$old])
        ->resetTableFilters()
        ->filterTable('status', ['draft'])
        ->assertCanNotSeeTableRecords([$today, $old]);
});

test('the date range filter resolves its presets', function () {
    $lastMonth = App\Filament\Admin\Filters\DateRangeFilter::resolvePreset('last_month');
    $thisYear = App\Filament\Admin\Filters\DateRangeFilter::resolvePreset('this_year');

    expect($lastMonth[0]->toDateString())->toBe(today()->subMonthNoOverflow()->startOfMonth()->toDateString())
        ->and($lastMonth[1]->toDateString())->toBe(today()->subMonthNoOverflow()->endOfMonth()->toDateString())
        ->and($thisYear[0]->toDateString())->toBe(today()->startOfYear()->toDateString())
        ->and(App\Filament\Admin\Filters\DateRangeFilter::resolvePreset('nonsense'))->toBeNull();
});

test('a customer statement lists posted sales with a running total', function () {
    $user = reportingOwner();
    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '10000', 'purchase_rate' => '100']), $user);

    $customer = Customer::factory()->create(['name' => 'Alpha Trading']);
    $other = Customer::factory()->create();
    $platform = Platform::factory()->create();

    reportingSale($user, $customer, $platform, '100', '120');
    reportingSale($user, $customer, $platform, '50', '130');
    reportingSale($user, $other, $platform, '999', '130');

    $report = app(ReportDataBuilder::class)->build([
        'report' => 'customer_statement',
        'customer_id' => $customer->id,
        'from' => today()->startOfMonth()->toDateString(),
        'to' => today()->toDateString(),
    ]);

    expect($report['title'])->toBe('Customer Statement — Alpha Trading')
        ->and($report['rows'])->toHaveCount(2)
        ->and($report['rows'][0][7])->toBe('12000.00')
        ->and($report['rows'][1][7])->toBe('18500.00')
        ->and($report['totals']['total_amount'])->toBe('18500.00')
        ->and($report['totals']['sales'])->toBe('2');
});

test('a customer statement asks for a customer before it builds', function () {
    $report = app(ReportDataBuilder::class)->build([
        'report' => 'customer_statement',
        'from' => today()->startOfMonth()->toDateString(),
        'to' => today()->toDateString(),
    ]);

    expect($report['rows'])->toBe([])
        ->and($report['totals']['message'])->toContain('Select a customer');
});

test('an employee statement interleaves payroll charges and payments into a balance', function () {
    $user = reportingOwner();
    $employee = Employee::factory()->create(['current_salary' => '2000']);

    $payroll = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-STMT-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '2000',
        'total_salary_expense' => '2000',
        'paid_amount' => '0',
        'due_amount' => '2000',
        'status' => 'unpaid',
    ]);

    app(RecordPayrollPaymentAction::class)->execute($payroll, '1200', $user, today()->toDateString(), 'Bank');

    $report = app(ReportDataBuilder::class)->build([
        'report' => 'employee_statement',
        'employee_id' => $employee->id,
        'from' => today()->startOfMonth()->toDateString(),
        'to' => today()->endOfMonth()->toDateString(),
    ]);

    expect($report['rows'])->toHaveCount(2)
        ->and($report['rows'][0][1])->toBe('Payroll')
        ->and($report['rows'][0][5])->toBe('2000.00')
        ->and($report['rows'][1][1])->toBe('Payment (Bank)')
        ->and($report['rows'][1][5])->toBe('800.00')
        ->and($report['totals']['outstanding'])->toBe('800.00');
});

test('the monthly summary totals each month and the range', function () {
    $user = reportingOwner();
    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '10000', 'purchase_rate' => '100']), $user);

    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    reportingSale($user, $customer, $platform, '100', '120');

    $category = ExpenseCategory::query()->create(['name' => 'Office']);
    Expense::query()->create([
        'expense_number' => 'EXP-SUM-001',
        'expense_date' => today()->toDateString(),
        'expense_category_id' => $category->id,
        'amount' => '500',
        'description' => 'Rent',
    ]);

    $report = app(ReportDataBuilder::class)->build([
        'report' => 'monthly_summary',
        'from' => today()->startOfMonth()->subMonths(2)->toDateString(),
        'to' => today()->endOfMonth()->toDateString(),
    ]);

    expect($report['rows'])->toHaveCount(3)
        ->and($report['totals']['revenue'])->toBe('12000.00')
        ->and($report['totals']['gross_profit'])->toBe('2000.00')
        ->and($report['totals']['office_expenses'])->toBe('500.00')
        ->and($report['totals']['net_profit'])->toBe('1500.00');
});

test('the inventory valuation report lists the layers still holding dollars', function () {
    $user = reportingOwner();
    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '100', 'purchase_rate' => '110']), $user);

    $report = app(ReportDataBuilder::class)->build([
        'report' => 'inventory_valuation',
        'from' => today()->subDay()->toDateString(),
        'to' => today()->toDateString(),
    ]);

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0][1])->toBe('purchase')
        ->and((float) $report['rows'][0][4])->toBe(100.0)
        ->and($report['rows'][0][7])->toBe('Open');
});

test('the reports, statements and analytics pages all render', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    Livewire::test(Reports::class)->assertOk()->assertSee('USD Sale Report');
    Livewire::test(Statements::class)->assertOk()->assertSee('Select a customer');
    Livewire::test(BusinessAnalytics::class)
        ->assertOk()
        ->assertSeeLivewire(App\Filament\Admin\Widgets\Analytics\AnalyticsSummaryStats::class)
        ->assertSeeLivewire(App\Filament\Admin\Widgets\Analytics\ProfitCompositionChart::class)
        ->assertSeeLivewire(App\Filament\Admin\Widgets\Analytics\RateSpreadChart::class);
});

test('a user without payroll permission cannot open an employee statement', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');
    $user->givePermissionTo('View:Reports', 'Export:Report');
    $this->actingAs($user);

    expect($user->can('ViewPayroll'))->toBeFalse();

    $this->get(route('reports.export', [
        'format' => 'csv',
        'report' => 'employee_statement',
        'employee_id' => Employee::factory()->create()->id,
        'from' => today()->startOfMonth()->toDateString(),
        'to' => today()->toDateString(),
    ]))->assertForbidden();
});

test('every resource list page renders with its summary widgets', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '1000', 'purchase_rate' => '100']), $user);
    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    reportingSale($user, $customer, $platform, '100', '120');

    $paths = [
        '/sales', '/usd-purchases', '/expenses', '/payrolls', '/customers', '/employees',
        '/invoices', '/inventory-transactions', '/audit-logs', '/platforms',
        '/expense-categories', '/users', '/reports', '/statements', '/business-analytics',
    ];

    $statuses = collect($paths)->mapWithKeys(fn (string $path): array => [$path => $this->get($path)->status()]);

    expect($statuses->filter(fn (int $status): bool => $status !== 200)->all())->toBe([]);
});

test('record pages render their own analytics widgets', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '1000', 'purchase_rate' => '100']), $user);
    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    $sale = reportingSale($user, $customer, $platform, '100', '120');
    $employee = Employee::factory()->create(['current_salary' => '1000']);
    $payroll = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-VIEW-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '1000',
        'total_salary_expense' => '1000',
        'paid_amount' => '0',
        'due_amount' => '1000',
        'status' => 'unpaid',
    ]);
    app(RecordPayrollPaymentAction::class)->execute($payroll, '400', $user, today()->toDateString(), 'Cash');

    $paths = [
        "/customers/{$customer->id}",
        "/platforms/{$platform->id}",
        "/sales/{$sale->id}",
        "/employees/{$employee->id}",
        "/payrolls/{$payroll->id}",
    ];

    $statuses = collect($paths)->mapWithKeys(fn (string $path): array => [$path => $this->get($path)->status()]);

    expect($statuses->filter(fn (int $status): bool => $status !== 200)->all())->toBe([]);
});

test('the customer account widget reports that customer only', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '10000', 'purchase_rate' => '100']), $user);
    $alpha = Customer::factory()->create();
    $beta = Customer::factory()->create();
    $platform = Platform::factory()->create();

    reportingSale($user, $alpha, $platform, '100', '120');
    reportingSale($user, $beta, $platform, '400', '130');

    Livewire::test(App\Filament\Admin\Resources\Customers\Widgets\CustomerAccountStats::class, ['record' => $alpha])
        ->assertSee('৳ 12,000.00')
        ->assertDontSee('৳ 52,000.00');
});

test('relation manager tables filter their own rows', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '10000', 'purchase_rate' => '100']), $user);

    $customer = Customer::factory()->create();
    $alpha = Platform::factory()->create(['name' => 'Alpha Platform']);
    $beta = Platform::factory()->create(['name' => 'Beta Platform']);

    $first = reportingSale($user, $customer, $alpha, '100', '120');
    $second = reportingSale($user, $customer, $beta, '200', '125');

    Livewire::test(App\Filament\Admin\Resources\Customers\RelationManagers\SalesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => App\Filament\Admin\Resources\Customers\Pages\ViewCustomer::class,
    ])
        ->assertCanSeeTableRecords([$first, $second])
        ->filterTable('platform', [$alpha->id])
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second]);
});

test('summary widgets never mix aggregates with the table select list', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '1000', 'purchase_rate' => '100']), $user);
    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    reportingSale($user, $customer, $platform, '100', '120');

    $employee = Employee::factory()->create(['current_salary' => '1000']);
    Payroll::query()->create([
        'payroll_number' => 'PAYROLL-AGG-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '1000',
        'total_salary_expense' => '1000',
        'paid_amount' => '0',
        'due_amount' => '1000',
        'status' => 'unpaid',
    ]);
    $category = ExpenseCategory::query()->create(['name' => 'Office']);
    Expense::query()->create([
        'expense_number' => 'EXP-AGG-001',
        'expense_date' => today()->toDateString(),
        'expense_category_id' => $category->id,
        'amount' => '500',
        'description' => 'Rent',
    ]);

    $widgets = [
        SaleStatsOverview::class,
        App\Filament\Admin\Resources\Sales\Widgets\SaleTrendChart::class,
        App\Filament\Admin\Resources\UsdPurchases\Widgets\UsdPurchaseStatsOverview::class,
        App\Filament\Admin\Resources\UsdPurchases\Widgets\UsdPurchaseTrendChart::class,
        App\Filament\Admin\Resources\Expenses\Widgets\ExpenseStatsOverview::class,
        App\Filament\Admin\Resources\Expenses\Widgets\ExpenseCategoryChart::class,
        App\Filament\Admin\Resources\Payrolls\Widgets\PayrollStatsOverview::class,
        App\Filament\Admin\Resources\Payrolls\Widgets\PayrollTrendChart::class,
        App\Filament\Admin\Resources\Customers\Widgets\CustomerStatsOverview::class,
        App\Filament\Admin\Resources\Customers\Widgets\CustomerRevenueChart::class,
        App\Filament\Admin\Resources\Employees\Widgets\EmployeeStatsOverview::class,
        App\Filament\Admin\Resources\Invoices\Widgets\InvoiceStatsOverview::class,
        App\Filament\Admin\Resources\InventoryTransactions\Widgets\InventoryFlowStatsOverview::class,
        App\Filament\Admin\Resources\InventoryTransactions\Widgets\InventoryFlowChart::class,
        App\Filament\Admin\Resources\AuditLogs\Widgets\AuditLogStatsOverview::class,
        App\Filament\Admin\Resources\AuditLogs\Widgets\AuditActionChart::class,
        App\Filament\Admin\Resources\Platforms\Widgets\PlatformStatsOverview::class,
        App\Filament\Admin\Resources\ExpenseCategories\Widgets\ExpenseCategoryStatsOverview::class,
        App\Filament\Admin\Resources\Users\Widgets\UserStatsOverview::class,
    ];

    $offending = [];

    DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use (&$offending): void {
        $sql = $query->sql;
        $selectList = Str::before(Str::after($sql, 'select '), ' from ');

        // MySQL rejects an aggregate sitting next to plain columns without a GROUP BY that
        // covers them. sqlite lets it through, so assert on the SQL instead of the result.
        if (! Str::contains($selectList, ['count(', 'COUNT(', 'sum(', 'SUM(', 'AVG(', 'MIN(', 'MAX('])) {
            return;
        }

        if (Str::contains($selectList, '.*') && ! Str::contains($sql, 'group by')) {
            $offending[] = $sql;
        }
    });

    foreach ($widgets as $widget) {
        Livewire::test($widget)->assertOk();
    }

    DB::flushQueryLog();

    expect($offending)->toBe([]);
});

test('list pages hand their live table state to the summary widgets', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    $required = [
        'paginators', 'tableColumnSearches', 'tableFilters',
        'tableGrouping', 'tableRecordsPerPage', 'tableSearch', 'tableSort',
    ];

    $pages = [
        ListSales::class,
        App\Filament\Admin\Resources\Invoices\Pages\ListInvoices::class,
        App\Filament\Admin\Resources\UsdPurchases\Pages\ListUsdPurchases::class,
        App\Filament\Admin\Resources\Expenses\Pages\ListExpenses::class,
        App\Filament\Admin\Resources\Payrolls\Pages\ListPayrolls::class,
        App\Filament\Admin\Resources\Customers\Pages\ListCustomers::class,
        App\Filament\Admin\Resources\Employees\Pages\ListEmployees::class,
        App\Filament\Admin\Resources\InventoryTransactions\Pages\ListInventoryTransactions::class,
        App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs::class,
        App\Filament\Admin\Resources\Platforms\Pages\ListPlatforms::class,
        App\Filament\Admin\Resources\ExpenseCategories\Pages\ListExpenseCategories::class,
        App\Filament\Admin\Resources\Users\Pages\ListUsers::class,
    ];

    foreach ($pages as $page) {
        // Livewire resolves an unpassed #[Reactive] prop to null, which fatals on the
        // non-nullable ones, so every list page has to hand its table state over by name.
        $data = Livewire::test($page)->instance()->getWidgetData();

        expect(array_keys($data))->toContain(...$required);
    }
});

test('changing a filter keeps the summary widgets alive', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '1000', 'purchase_rate' => '100']), $user);
    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    reportingSale($user, $customer, $platform, '100', '120');

    $page = Livewire::test(App\Filament\Admin\Resources\Invoices\Pages\ListInvoices::class)
        ->filterTable('invoice_date', ['preset' => 'yesterday'])
        ->filterTable('cancelled_at', false)
        ->assertOk();

    // The applied filters have to travel to the widgets, otherwise they summarise every row.
    $data = $page->instance()->getWidgetData();

    expect($data['tableFilters']['cancelled_at']['value'])->toBeFalse()
        ->and($data['tableColumnSearches'])->toBeArray();

    $this->get('/invoices?filters[invoice_date][preset]=yesterday&filters[cancelled_at][value]=0')
        ->assertOk();

    // The reactive contract: a null column-search payload must not reach a typed array.
    Livewire::test(App\Filament\Admin\Resources\Invoices\Widgets\InvoiceStatsOverview::class, [
        'tableFilters' => ['status' => ['values' => ['issued']]],
        'tableColumnSearches' => [],
    ])->assertOk();
});

test('filter forms are laid out wide rather than squeezed', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    $table = Livewire::test(App\Filament\Admin\Resources\Invoices\Pages\ListInvoices::class)
        ->instance()
        ->getTable();

    expect($table->getFiltersFormWidth())->toBe(Filament\Support\Enums\Width::FiveExtraLarge)
        ->and($table->getFiltersFormColumns())->toBe(['default' => 1, 'sm' => 2, 'xl' => 3]);

    $dateFilter = $table->getFilter('invoice_date');

    // A from/until pair needs the whole row, otherwise each picker lands in a ~110px column.
    expect($dateFilter->getColumnSpan())->toBe(['default' => 'full']);
});

test('the analytics filter bar spans the page and sits above the charts', function () {
    $user = reportingOwner();
    $this->actingAs($user);

    $page = Livewire::test(BusinessAnalytics::class)->instance();

    // Header widgets render before the view, footer widgets after it, so the charts belong
    // in the footer for the filter bar to come first.
    $headerWidgets = (fn (): array => $this->getHeaderWidgets())->call($page);

    expect($headerWidgets)->toBe([])
        ->and($page->getFooterWidgets())->toContain(App\Filament\Admin\Widgets\Analytics\RateSpreadChart::class);

    $section = $page->getFiltersForm()->getComponents()[0];

    // The filters schema is a 4-column grid; without a full span the section is a quarter wide.
    expect($section->getColumnSpan())->toBe(['default' => 'full']);
});

test('the rate spread chart reports buy rate, sell rate and the gap', function () {
    $user = reportingOwner();

    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create([
        'usd_quantity' => '1000',
        'purchase_rate' => '110',
        'purchase_date' => today()->toDateString(),
    ]), $user);

    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    reportingSale($user, $customer, $platform, '100', '125');

    $this->actingAs($user);

    $chart = Livewire::test(App\Filament\Admin\Widgets\Analytics\RateSpreadChart::class, [
        'pageFilters' => ['from' => today()->toDateString(), 'to' => today()->toDateString(), 'granularity' => 'day'],
    ])->instance();

    $data = (fn (): array => $this->getCachedData())->call($chart);

    expect($data['labels'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'][0])->toBe('110.00000000')
        ->and($data['datasets'][1]['data'][0])->toBe('125.00000000')
        ->and($data['datasets'][2]['data'][0])->toBe('15.00000000');
});
