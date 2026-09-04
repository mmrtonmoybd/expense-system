<?php

declare(strict_types=1);

use App\Actions\PostSaleAction;
use App\Actions\PostUsdPurchaseAction;
use App\Filament\Admin\Pages\Reports;
use App\Models\CustomerPlatform;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\ReportDataBuilder;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

function postedSaleForReports(User $user): Sale
{
    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(), $user);
    $sale = Sale::factory()->create(['sale_date' => today()->toDateString()]);
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create(['customer_id' => $sale->customer_id, 'platform_id' => $platform->id, 'current_selling_rate' => 122]);
    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => '100',
        'selling_rate' => '122',
        'weighted_cost_rate' => 0,
        'revenue' => 0,
        'inventory_cost' => 0,
        'gross_profit' => 0,
    ]);

    return app(PostSaleAction::class)->execute($sale, $user);
}

test('profit and loss excludes purchases and counts salary expense once', function () {
    $user = User::factory()->create();
    $sale = postedSaleForReports($user);
    expect($sale->status)->toBe('posted')
        ->and($sale->sale_date->toDateString())->toBe(today()->toDateString())
        ->and($sale->total_bdt_revenue)->toBe('12200.00');
    $category = ExpenseCategory::query()->create(['name' => 'Office']);
    Expense::query()->create([
        'expense_number' => 'EXP-TEST-001',
        'expense_date' => today()->toDateString(),
        'expense_category_id' => $category->id,
        'amount' => '500',
        'description' => 'Office expense',
    ]);
    $employee = Employee::query()->create([
        'employee_code' => 'EMP-REPORT',
        'name' => 'Report Employee',
        'phone' => '01800000002',
        'designation' => 'Reporter',
        'joining_date' => today()->toDateString(),
        'current_salary' => '1000',
    ]);
    Payroll::query()->create([
        'payroll_number' => 'PAYROLL-REPORT-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '1000',
        'total_salary_expense' => '1000',
        'paid_amount' => '400',
        'due_amount' => '600',
        'status' => 'partially_paid',
    ]);

    // A whole calendar month, so the month's payroll is accrued in full exactly once.
    $report = app(FinancialReportService::class)->profitAndLoss(today()->startOfMonth(), today()->endOfMonth());

    expect($report)->toMatchArray([
        'revenue' => '12200.00',
        'cost_of_usd_sold' => '11100.00',
        'gross_profit' => '1100.00',
        'office_expenses' => '500.00',
        'salary_expense' => '1000.00',
        'net_profit' => '-400.00',
    ]);
});

test('invoice html and pdf hide all internal financial values', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    $sale = postedSaleForReports($user);
    $invoice = Invoice::query()->whereBelongsTo($sale)->sole();

    $print = $this->actingAs($user)->get(route('invoices.print', $invoice));
    $print->assertOk()->assertSee($invoice->invoice_number)
        ->assertSee('Billed to')
        ->assertSee('Invoice details')
        ->assertSee('Grand Total')
        ->assertDontSee('Inventory Cost')
        ->assertDontSee('Gross Profit')
        ->assertDontSee('Weighted Cost');

    $this->get(route('invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('filtered reports export xlsx csv pdf and print', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    postedSaleForReports($user);
    $query = ['report' => 'sales', 'from' => today()->toDateString(), 'to' => today()->toDateString()];

    $this->actingAs($user)->get(route('reports.export', ['format' => 'xlsx'] + $query))->assertOk();
    $csv = $this->get(route('reports.export', ['format' => 'csv'] + $query))->assertOk();
    expect($csv->streamedContent())->toStartWith("\xEF\xBB\xBF");
    $this->get(route('reports.export', ['format' => 'pdf'] + $query))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('reports.export', ['format' => 'print'] + $query))->assertOk()->assertSee('Sale Report');
});

test('customer and platform performance reports apply every selected filter', function () {
    $user = User::factory()->create();
    $firstSale = postedSaleForReports($user);
    $secondSale = postedSaleForReports($user);
    $firstPlatformId = $firstSale->lines()->value('platform_id');

    $customerReport = app(ReportDataBuilder::class)->build([
        'report' => 'customers',
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
        'customer_id' => $firstSale->customer_id,
    ], perPage: 50);
    $platformReport = app(ReportDataBuilder::class)->build([
        'report' => 'platforms',
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
        'platform_id' => $firstPlatformId,
    ], perPage: 50);

    expect($customerReport['rows'])->toHaveCount(1)
        ->and($customerReport['rows'][0][0])->toBe($firstSale->customer->name)
        ->and($customerReport['rows'][0][0])->not->toBe($secondSale->customer->name)
        ->and($customerReport['pagination']['total'])->toBe(1)
        ->and($platformReport['rows'])->toHaveCount(1)
        ->and($platformReport['pagination']['total'])->toBe(1)
        ->and($platformReport['rows'][0][0])->toBe($firstSale->lines->first()->platform->name);
});

test('report page shows only filters related to the selected report', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    $this->actingAs($user);

    Livewire::test(Reports::class)
        ->assertFormFieldVisible('customer_id')
        ->assertFormFieldVisible('platform_id')
        ->assertFormFieldHidden('expense_category_id')
        ->assertFormFieldHidden('employee_id')
        ->fillForm(['report' => 'expenses'])
        ->assertFormFieldVisible('expense_category_id')
        ->assertFormFieldHidden('customer_id')
        ->assertFormFieldHidden('platform_id')
        ->assertFormFieldHidden('employee_id');
});

test('report rows use database pagination', function () {
    $user = User::factory()->create();
    postedSaleForReports($user);
    postedSaleForReports($user);
    postedSaleForReports($user);

    $report = app(ReportDataBuilder::class)->build([
        'report' => 'sales',
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
    ], perPage: 2);

    expect($report['rows'])->toHaveCount(2)
        ->and($report['pagination'])->toMatchArray([
            'current_page' => 1,
            'last_page' => 2,
            'per_page' => 2,
            'total' => 3,
        ]);
});
