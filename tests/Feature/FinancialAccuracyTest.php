<?php

declare(strict_types=1);

use App\Actions\AdjustInventoryAction;
use App\Actions\ChangeCostingMethodAction;
use App\Actions\PostSaleAction;
use App\Actions\PostUsdPurchaseAction;
use App\Actions\RecalculatePendingSaleAction;
use App\Actions\RecordPayrollPaymentAction;
use App\Actions\ReverseSaleAction;
use App\Actions\SubmitSaleAction;
use App\Filament\Admin\Resources\Payrolls\Pages\EditPayroll;
use App\Filament\Admin\Resources\Sales\Pages\CreateSale;
use App\Filament\Admin\Resources\Sales\Pages\EditSale;
use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\Employee;
use App\Models\InventoryLayer;
use App\Models\InventoryState;
use App\Models\Payroll;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleCostAllocation;
use App\Models\SaleLine;
use App\Models\Setting;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\ProfitPredictor;
use App\Services\ReportDataBuilder;
use App\Services\UsdInventoryLayerService;
use App\Support\CostingMethod;
use App\Support\Money;
use App\Support\PayrollCalculator;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

function accuracyUser(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function accuracyStock(User $user, string $quantity = '10000', string $rate = '100'): UsdPurchase
{
    return app(PostUsdPurchaseAction::class)->execute(
        UsdPurchase::factory()->create(['usd_quantity' => $quantity, 'purchase_rate' => $rate]),
        $user,
    );
}

/** @param array<int, array{quantity:string, rate:string}> $lines */
function accuracySale(User $user, array $lines, ?Customer $customer = null): Sale
{
    $sale = Sale::factory()->create([
        'created_by' => $user->id,
        ...($customer ? ['customer_id' => $customer->id] : []),
    ]);

    foreach ($lines as $line) {
        $platform = Platform::factory()->create();
        CustomerPlatform::query()->create([
            'customer_id' => $sale->customer_id,
            'platform_id' => $platform->id,
            'current_selling_rate' => $line['rate'],
        ]);
        SaleLine::query()->create([
            'sale_id' => $sale->id,
            'platform_id' => $platform->id,
            'usd_quantity' => $line['quantity'],
            'selling_rate' => $line['rate'],
            'weighted_cost_rate' => '0',
            'revenue' => '0',
            'inventory_cost' => '0',
            'gross_profit' => '0',
        ]);
    }

    return $sale->load('lines');
}

test('a submitted pending sale rounds its revenue half up exactly like the posted sale', function () {
    $user = accuracyUser(['Post:Sale']);
    accuracyStock($user);
    Setting::query()->create(['key' => 'sale_approval_required', 'value' => '1']);

    $sale = accuracySale($user, [
        ['quantity' => '3', 'rate' => '100.125'],
        ['quantity' => '7', 'rate' => '100.125'],
    ]);

    $pending = app(SubmitSaleAction::class)->execute($sale, $user);

    // 3 x 100.125 = 300.375 -> 300.38 and 7 x 100.125 = 700.875 -> 700.88
    expect($pending->status)->toBe('pending')
        ->and($pending->total_bdt_revenue)->toBe('1001.26');

    $posted = app(PostSaleAction::class)->execute($pending, $user);

    expect($posted->total_bdt_revenue)->toBe($pending->total_bdt_revenue)
        ->and($posted->sell_amount)->toBe('1001.26');
});

test('recalculating a pending sale rounds its revenue half up', function () {
    $user = accuracyUser(['Post:Sale']);
    accuracyStock($user);
    Setting::query()->create(['key' => 'sale_approval_required', 'value' => '1']);

    $sale = accuracySale($user, [['quantity' => '3', 'rate' => '100.125']]);
    app(SubmitSaleAction::class)->execute($sale, $user);

    $recalculated = app(RecalculatePendingSaleAction::class)->execute($sale->refresh(), $user);

    expect($recalculated->total_bdt_revenue)->toBe('300.38');
});

test('the profit predictor rounds revenue, cost and margin half up', function () {
    $user = accuracyUser();
    accuracyStock($user, '1000', '100.125');

    $result = app(ProfitPredictor::class)->calculate('3', '120.375');

    // 3 x 120.375 = 361.125 -> 361.13, 3 x 100.125 = 300.375 -> 300.38
    expect($result['revenue'])->toBe('361.13')
        ->and($result['inventory_cost'])->toBe('300.38')
        ->and($result['gross_profit'])->toBe('60.75');
});

test('the payroll total rounds half up instead of truncating', function () {
    expect(app(PayrollCalculator::class)->total([
        'base_salary' => '1000.00',
        'allowance' => '100.125',
        'bonus' => '0',
        'deduction' => '0',
        'adjustment' => '0',
    ]))->toBe('1100.13');
});

test('editing a payroll recalculates its payment status', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    $this->actingAs($user);

    $employee = Employee::factory()->create(['current_salary' => '1000']);
    $payroll = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-EDIT-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '1000',
        'total_salary_expense' => '1000',
        'paid_amount' => '1000',
        'due_amount' => '0',
        'status' => 'paid',
    ]);

    Livewire::test(EditPayroll::class, ['record' => $payroll->id])
        ->fillForm(['bonus' => 500])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($payroll->refresh()->total_salary_expense)->toBe('1500.00')
        ->and($payroll->due_amount)->toBe('500.00')
        ->and($payroll->status)->toBe('partially_paid');
});

test('platform performance reports a usd weighted average selling rate', function () {
    $user = accuracyUser();
    accuracyStock($user);

    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create([
        'customer_id' => $customer->id,
        'platform_id' => $platform->id,
        'current_selling_rate' => '120',
    ]);

    foreach ([['100', '120'], ['300', '124']] as [$quantity, $rate]) {
        $sale = Sale::factory()->create(['customer_id' => $customer->id, 'created_by' => $user->id]);
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
        app(PostSaleAction::class)->execute($sale, $user);
    }

    $report = app(ReportDataBuilder::class)->build([
        'report' => 'platforms',
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
    ]);

    // (100 x 120 + 300 x 124) / 400 = 123, not the unweighted mean of 122.
    expect($report['rows'])->toHaveCount(1)
        ->and(round((float) $report['rows'][0][5], 4))->toBe(123.0);
});

test('csv exports neutralise spreadsheet formula injection', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');

    $customer = Customer::factory()->create(['name' => '=1+1']);
    $seller = accuracyUser();
    accuracyStock($seller);
    $sale = accuracySale($seller, [['quantity' => '10', 'rate' => '120']], $customer);
    app(PostSaleAction::class)->execute($sale, $seller);

    $csv = $this->actingAs($user)->get(route('reports.export', [
        'format' => 'csv',
        'report' => 'sales',
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
    ]))->assertOk();

    expect($csv->streamedContent())->toContain("'=1+1")
        ->and($csv->streamedContent())->not->toContain(',=1+1');
});

test('a user without profit permission cannot read profit figures through reports', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->can('Export:Report'))->toBeTrue()
        ->and($user->can('ViewProfit'))->toBeFalse();

    $seller = accuracyUser();
    accuracyStock($seller);
    $sale = accuracySale($seller, [['quantity' => '10', 'rate' => '120']]);
    app(PostSaleAction::class)->execute($sale, $seller);

    $this->actingAs($user)->get(route('reports.export', [
        'format' => 'csv',
        'report' => 'pnl',
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
    ]))->assertForbidden();

    $csv = $this->actingAs($user)->get(route('reports.export', [
        'format' => 'csv',
        'report' => 'sales',
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
    ]))->assertOk();

    expect($csv->streamedContent())->not->toContain('Gross Profit');
});

test('the finance dashboard never charges a whole month of salary to a single day', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    $this->actingAs($user);

    $employee = Employee::factory()->create(['current_salary' => '3100']);
    Payroll::query()->create([
        'payroll_number' => 'PAYROLL-STAT-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '3100',
        'total_salary_expense' => '3100',
        'paid_amount' => '0',
        'due_amount' => '3100',
        'status' => 'unpaid',
    ]);

    $dailySalary = Money::divideBdt('3100', (string) today()->daysInMonth);

    Livewire::test(App\Filament\Admin\Widgets\FinanceStats::class)
        ->assertSee('৳ -'.number_format((float) $dailySalary, 2));
});

test('a negative adjustment under average costing keeps the weighted average intact', function () {
    $user = accuracyUser();
    accuracyStock($user, '100', '100');
    accuracyStock($user, '100', '120');

    expect(InventoryState::query()->sole()->weighted_average_cost)->toBe('110.00000000');

    app(AdjustInventoryAction::class)->execute('negative_adjustment', '100', $user, 'Bank correction');

    $state = InventoryState::query()->sole();

    expect($state->usd_balance)->toBe('100.00000000')
        ->and($state->inventory_value_bdt)->toBe('11000.00')
        ->and($state->weighted_average_cost)->toBe('110.00000000');
});

test('a sale cannot post the same platform twice', function () {
    $user = accuracyUser();
    accuracyStock($user);

    $sale = accuracySale($user, [['quantity' => '10', 'rate' => '120']]);
    $line = $sale->lines->first();
    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $line->platform_id,
        'usd_quantity' => '5',
        'selling_rate' => '120',
        'weighted_cost_rate' => '0',
        'revenue' => '0',
        'inventory_cost' => '0',
        'gross_profit' => '0',
    ]);

    expect(fn () => app(PostSaleAction::class)->execute($sale->refresh(), $user))
        ->toThrow(LogicException::class);

    expect(Sale::query()->whereKey($sale->id)->value('status'))->toBe('draft');
});

test('inventory layers reconcile with the authoritative position through a full fifo lifecycle', function () {
    $user = accuracyUser(['Manage:CostingMethod']);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);

    $layers = app(UsdInventoryLayerService::class);
    $reconciles = function () use ($layers): bool {
        $state = InventoryState::query()->sole();

        return $layers->matchesPosition((string) $state->usd_balance, (string) $state->inventory_value_bdt);
    };

    accuracyStock($user, '100', '100');
    accuracyStock($user, '200', '110');
    expect($reconciles())->toBeTrue();

    $sale = accuracySale($user, [['quantity' => '150', 'rate' => '125']]);
    $posted = app(PostSaleAction::class)->execute($sale, $user);

    // FIFO: 100 x 100 + 50 x 110 = 15,500
    expect($posted->total_inventory_cost)->toBe('15500.00')
        ->and($posted->total_gross_profit)->toBe('3250.00')
        ->and($reconciles())->toBeTrue();

    app(AdjustInventoryAction::class)->execute('positive_adjustment', '50', $user, 'Found funds');
    expect($reconciles())->toBeTrue();

    app(AdjustInventoryAction::class)->execute('negative_adjustment', '20', $user, 'Bank fee');
    expect($reconciles())->toBeTrue();

    app(ReverseSaleAction::class)->execute($posted, $user, 'Customer cancelled');
    expect($reconciles())->toBeTrue();

    $state = InventoryState::query()->sole();
    expect($state->usd_balance)->toBe('330.00000000');
});

test('average costing keeps the layer quantity equal to the authoritative balance', function () {
    $user = accuracyUser();
    accuracyStock($user, '100', '100');
    accuracyStock($user, '200', '110');

    accuracySale($user, [['quantity' => '150', 'rate' => '125']]);
    app(PostSaleAction::class)->execute(Sale::query()->latest('id')->sole(), $user);

    $state = InventoryState::query()->sole();

    expect(app(UsdInventoryLayerService::class)->remainingQuantity())->toBe((string) $state->usd_balance)
        // 32,000 / 300 = 106.66666667 per USD, so 150 USD costs 16,000.00
        ->and($state->inventory_value_bdt)->toBe('16000.00')
        ->and($state->weighted_average_cost)->toBe('106.66666667');
});

test('switching costing method rebases the layers onto the authoritative position', function () {
    $user = accuracyUser(['Manage:CostingMethod']);
    accuracyStock($user, '100', '100');
    accuracyStock($user, '200', '110');
    accuracySale($user, [['quantity' => '150', 'rate' => '125']]);
    app(PostSaleAction::class)->execute(Sale::query()->latest('id')->sole(), $user);

    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);

    $state = InventoryState::query()->sole();
    $layers = app(UsdInventoryLayerService::class);

    expect($layers->matchesPosition((string) $state->usd_balance, (string) $state->inventory_value_bdt))->toBeTrue()
        ->and($layers->remainingCost())->toBe('16000.00');
});

test('a payroll payment is stored at the precision the ledger keeps', function () {
    $user = accuracyUser();
    $employee = Employee::factory()->create(['current_salary' => '2000']);
    $payroll = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-PRECISION-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '2000',
        'total_salary_expense' => '2000',
        'paid_amount' => '0',
        'due_amount' => '2000',
        'status' => 'unpaid',
    ]);

    app(RecordPayrollPaymentAction::class)->execute($payroll, '1000.555', $user, today()->toDateString());

    $payroll->refresh();

    expect($payroll->paid_amount)->toBe('1000.56')
        ->and($payroll->due_amount)->toBe('999.44')
        ->and($payroll->status)->toBe('partially_paid')
        ->and((string) $payroll->payments()->sum('amount'))->toEqual($payroll->paid_amount);
});

test('salary expense is prorated across the reported range and never double counted', function () {
    $user = accuracyUser();
    $employee = Employee::factory()->create(['current_salary' => '3100']);
    $month = Carbon::parse('2026-01-01');

    Payroll::query()->create([
        'payroll_number' => 'PAYROLL-PRORATE-001',
        'employee_id' => $employee->id,
        'payroll_month' => $month->toDateString(),
        'base_salary' => '3100',
        'total_salary_expense' => '3100',
        'paid_amount' => '0',
        'due_amount' => '3100',
        'status' => 'unpaid',
    ]);

    $reports = app(FinancialReportService::class);
    $wholeMonth = $reports->profitAndLoss($month, $month->copy()->endOfMonth());
    $firstHalf = $reports->profitAndLoss($month, Carbon::parse('2026-01-15'));
    $secondHalf = $reports->profitAndLoss(Carbon::parse('2026-01-16'), $month->copy()->endOfMonth());
    $before = $reports->profitAndLoss(Carbon::parse('2025-12-01'), Carbon::parse('2025-12-31'));

    // 3,100 over 31 days = 100 per day.
    expect($wholeMonth['salary_expense'])->toBe('3100.00')
        ->and($firstHalf['salary_expense'])->toBe('1500.00')
        ->and($secondHalf['salary_expense'])->toBe('1600.00')
        ->and(bcadd($firstHalf['salary_expense'], $secondHalf['salary_expense'], 2))->toBe($wholeMonth['salary_expense'])
        ->and($before['salary_expense'])->toBe('0.00');
});

test('a rounded salary proration still sums back to the full month', function () {
    $user = accuracyUser();
    $employee = Employee::factory()->create(['current_salary' => '1000']);
    $month = Carbon::parse('2026-01-01');

    Payroll::query()->create([
        'payroll_number' => 'PAYROLL-PRORATE-002',
        'employee_id' => $employee->id,
        'payroll_month' => $month->toDateString(),
        'base_salary' => '1000',
        'total_salary_expense' => '1000',
        'paid_amount' => '0',
        'due_amount' => '1000',
        'status' => 'unpaid',
    ]);

    $reports = app(FinancialReportService::class);
    $slices = [
        $reports->profitAndLoss($month, Carbon::parse('2026-01-10'))['salary_expense'],
        $reports->profitAndLoss(Carbon::parse('2026-01-11'), Carbon::parse('2026-01-20'))['salary_expense'],
        $reports->profitAndLoss(Carbon::parse('2026-01-21'), $month->copy()->endOfMonth())['salary_expense'],
    ];

    expect(array_reduce($slices, fn (string $carry, string $slice): string => bcadd($carry, $slice, 2), '0.00'))
        ->toBe('1000.00');
});

test('a user without the override permission cannot store a tampered selling rate', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('employee');
    $this->actingAs($user);

    expect($user->can('OverrideRate:Sale'))->toBeFalse();

    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create([
        'customer_id' => $customer->id,
        'platform_id' => $platform->id,
        'current_selling_rate' => '120',
    ]);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'lines' => [['platform_id' => $platform->id, 'usd_quantity' => '10', 'selling_rate' => '999']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $line = SaleLine::query()->sole();

    expect($line->selling_rate)->toBe('120.00000000');

    Livewire::test(EditSale::class, ['record' => $line->sale_id])
        ->fillForm(['lines' => [[
            'id' => (string) $line->id,
            'platform_id' => $platform->id,
            'usd_quantity' => '10',
            'selling_rate' => '888',
        ]]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SaleLine::query()->sole()->selling_rate)->toBe('120.00000000');
});

test('reversing an average costed sale returns the position as a single layer', function () {
    $user = accuracyUser();
    accuracyStock($user, '100', '100');
    accuracyStock($user, '200', '110');

    $sale = accuracySale($user, [['quantity' => '150', 'rate' => '125']]);
    $posted = app(PostSaleAction::class)->execute($sale, $user);

    expect(SaleCostAllocation::query()->where('sale_id', $posted->id)->count())->toBe(0);

    app(ReverseSaleAction::class)->execute($posted, $user, 'Customer cancelled');

    $state = InventoryState::query()->sole();

    expect($state->usd_balance)->toBe('300.00000000')
        ->and($state->inventory_value_bdt)->toBe('32000.00')
        ->and(InventoryLayer::query()->where('origin_type', 'reversal')->count())->toBe(1)
        ->and(app(UsdInventoryLayerService::class)->remainingQuantity())->toBe((string) $state->usd_balance);
});

test('reversing a fifo sale returns the dollars to the layers they came from', function () {
    $user = accuracyUser(['Manage:CostingMethod']);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);

    accuracyStock($user, '100', '100');
    accuracyStock($user, '200', '110');

    $sale = accuracySale($user, [['quantity' => '150', 'rate' => '125']]);
    $posted = app(PostSaleAction::class)->execute($sale, $user);

    app(ReverseSaleAction::class)->execute($posted, $user, 'Customer cancelled');

    $layers = InventoryLayer::query()->orderBy('id')->get();
    $state = InventoryState::query()->sole();

    expect($layers)->toHaveCount(2)
        ->and($layers[0]->remaining_quantity)->toBe('100.00000000')
        ->and($layers[0]->remaining_cost_bdt)->toBe('10000.00')
        ->and($layers[0]->is_closed)->toBeFalse()
        ->and($layers[1]->remaining_quantity)->toBe('200.00000000')
        ->and($layers[1]->remaining_cost_bdt)->toBe('22000.00')
        ->and($state->usd_balance)->toBe('300.00000000')
        ->and($state->inventory_value_bdt)->toBe('32000.00');

    // The oldest layer is available again, so the next FIFO sale costs 100 per USD.
    $next = accuracySale($user, [['quantity' => '100', 'rate' => '125']]);

    expect(app(PostSaleAction::class)->execute($next, $user)->total_inventory_cost)->toBe('10000.00');
});

test('a user with the override permission can still set a custom selling rate', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    $this->actingAs($user);

    expect($user->can('OverrideRate:Sale'))->toBeTrue();

    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create([
        'customer_id' => $customer->id,
        'platform_id' => $platform->id,
        'current_selling_rate' => '120',
    ]);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'lines' => [['platform_id' => $platform->id, 'usd_quantity' => '10', 'selling_rate' => '131.5']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SaleLine::query()->sole()->selling_rate)->toBe('131.50000000');
});
