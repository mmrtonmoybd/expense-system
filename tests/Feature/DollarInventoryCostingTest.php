<?php

declare(strict_types=1);

use App\Actions\ChangeCostingMethodAction;
use App\Actions\PostSaleAction;
use App\Actions\PostUsdPurchaseAction;
use App\Actions\UpdateUsdSellRateAction;
use App\Filament\Admin\Widgets\FinanceStats;
use App\Models\CostingMethodChange;
use App\Models\CustomerPlatform;
use App\Models\InventoryLayer;
use App\Models\InventoryState;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleCostAllocation;
use App\Models\SaleLine;
use App\Models\UsdPurchase;
use App\Models\UsdSellRate;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\ReportDataBuilder;
use App\Services\UsdInventoryLayerService;
use App\Services\UsdSellRateService;
use App\Support\CostingMethod;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

function costingUser(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function postCostingPurchase(User $user, string $quantity, string $rate): UsdPurchase
{
    return app(PostUsdPurchaseAction::class)->execute(
        UsdPurchase::factory()->create(['usd_quantity' => $quantity, 'purchase_rate' => $rate]),
        $user,
    );
}

function postCostingSale(User $user, string $quantity, string $sellRate = '125.00000000'): Sale
{
    $sale = Sale::factory()->create(['created_by' => $user->id]);
    $platform = Platform::factory()->create();

    CustomerPlatform::query()->create([
        'customer_id' => $sale->customer_id,
        'platform_id' => $platform->id,
        'current_selling_rate' => $sellRate,
    ]);
    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => $quantity,
        'selling_rate' => $sellRate,
        'weighted_cost_rate' => '0',
        'revenue' => '0',
        'inventory_cost' => '0',
        'gross_profit' => '0',
    ]);

    return app(PostSaleAction::class)->execute($sale, $user);
}

test('multiple purchases and a sale use moving weighted average snapshots', function () {
    $user = costingUser();
    postCostingPurchase($user, '1000.00000000', '112.00000000');
    postCostingPurchase($user, '2000.00000000', '120.00000000');

    $sale = postCostingSale($user, '1000.00000000');

    expect($sale->costing_method)->toBe(CostingMethod::Average)
        ->and($sale->cost_rate)->toBe('117.33333333')
        ->and($sale->cost_amount)->toBe('117333.33')
        ->and($sale->sell_amount)->toBe('125000.00')
        ->and($sale->profit_amount)->toBe('7666.67')
        ->and($sale->costing_calculated_at)->not->toBeNull()
        ->and($sale->lines()->sole()->costing_method)->toBe(CostingMethod::Average)
        ->and(InventoryState::query()->sole()->usd_balance)->toBe('2000.00000000');
});

test('FIFO sale crosses oldest purchase layers and stores its audit breakdown', function () {
    $user = costingUser(['Manage:CostingMethod']);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);
    $first = postCostingPurchase($user, '500.00000000', '112.00000000');
    $second = postCostingPurchase($user, '1000.00000000', '118.00000000');
    $third = postCostingPurchase($user, '2000.00000000', '122.00000000');

    $sale = postCostingSale($user, '2000.00000000');
    $allocations = $sale->costAllocations()->orderBy('id')->get();

    expect($sale->costing_method)->toBe(CostingMethod::Fifo)
        ->and($sale->cost_amount)->toBe('235000.00')
        ->and($sale->cost_rate)->toBe('117.50000000')
        ->and($sale->profit_amount)->toBe('15000.00')
        ->and($allocations)->toHaveCount(3)
        ->and($allocations->pluck('usd_purchase_id')->all())->toBe([$first->id, $second->id, $third->id])
        ->and($allocations->pluck('quantity_used')->all())->toBe(['500.00000000', '1000.00000000', '500.00000000'])
        ->and(InventoryLayer::query()->where('usd_purchase_id', $third->id)->sole()->remaining_quantity)->toBe('1500.00000000');
});

test('LIFO sale crosses newest purchase layers and stores its audit breakdown', function () {
    $user = costingUser(['Manage:CostingMethod']);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Lifo, $user);
    $first = postCostingPurchase($user, '500.00000000', '112.00000000');
    $second = postCostingPurchase($user, '1000.00000000', '118.00000000');
    $third = postCostingPurchase($user, '2000.00000000', '122.00000000');

    $sale = postCostingSale($user, '2500.00000000', '130.00000000');
    $allocations = $sale->costAllocations()->orderBy('id')->get();

    expect($sale->costing_method)->toBe(CostingMethod::Lifo)
        ->and($sale->cost_amount)->toBe('303000.00')
        ->and($sale->cost_rate)->toBe('121.20000000')
        ->and($allocations->pluck('usd_purchase_id')->all())->toBe([$third->id, $second->id])
        ->and($allocations->pluck('quantity_used')->all())->toBe(['2000.00000000', '500.00000000'])
        ->and(InventoryLayer::query()->where('usd_purchase_id', $first->id)->sole()->remaining_quantity)->toBe('500.00000000');
});

test('Average to FIFO creates a reconciled opening layer without changing the old sale', function () {
    $user = costingUser(['Manage:CostingMethod']);
    postCostingPurchase($user, '1000.00000000', '112.00000000');
    postCostingPurchase($user, '1000.00000000', '118.00000000');
    $averageSale = postCostingSale($user, '500.00000000');
    $originalSnapshot = $averageSale->only(['costing_method', 'cost_rate', 'cost_amount', 'profit_amount']);
    $originalCalculatedAt = $averageSale->costing_calculated_at?->toISOString();

    $change = app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user, 'Policy change');

    expect($change->old_method)->toBe(CostingMethod::Average)
        ->and($change->new_method)->toBe(CostingMethod::Fifo)
        ->and($change->opening_layer_id)->not->toBeNull()
        ->and($change->usd_balance_snapshot)->toBe('1500.00000000')
        ->and($change->inventory_value_snapshot)->toBe('172500.00')
        ->and($averageSale->refresh()->only(array_keys($originalSnapshot)))->toBe($originalSnapshot)
        ->and($averageSale->costing_calculated_at?->toISOString())->toBe($originalCalculatedAt)
        ->and(app(UsdInventoryLayerService::class)->remainingQuantity())->toBe('1500.00000000')
        ->and(app(UsdInventoryLayerService::class)->remainingCost())->toBe('172500.00');
});

test('FIFO to LIFO preserves reconciled purchase layers and prior FIFO profit', function () {
    $user = costingUser(['Manage:CostingMethod']);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);
    postCostingPurchase($user, '1000.00000000', '100.00000000');
    $newest = postCostingPurchase($user, '1000.00000000', '120.00000000');
    $fifoSale = postCostingSale($user, '500.00000000');
    $originalProfit = $fifoSale->profit_amount;

    $change = app(ChangeCostingMethodAction::class)->execute(CostingMethod::Lifo, $user);
    $lifoSale = postCostingSale($user, '500.00000000');

    expect($change->opening_layer_id)->toBeNull()
        ->and($fifoSale->refresh()->costing_method)->toBe(CostingMethod::Fifo)
        ->and($fifoSale->profit_amount)->toBe($originalProfit)
        ->and($lifoSale->costing_method)->toBe(CostingMethod::Lifo)
        ->and($lifoSale->cost_amount)->toBe('60000.00')
        ->and($lifoSale->costAllocations()->sole()->usd_purchase_id)->toBe($newest->id);
});

test('method changes preserve inventory quantity and record an immutable audit history', function () {
    $user = costingUser(['Manage:CostingMethod']);
    postCostingPurchase($user, '750.12345678', '118.25000000');

    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Lifo, $user);

    expect(CostingMethodChange::query()->count())->toBe(2)
        ->and(CostingMethodChange::query()->latest('id')->firstOrFail()->changed_by)->toBe($user->id)
        ->and(InventoryState::query()->sole()->usd_balance)->toBe('750.12345678')
        ->and(app(UsdInventoryLayerService::class)->remainingQuantity())->toBe('750.12345678');
});

test('profit reports sum sale snapshots rather than mutable legacy totals or current method', function () {
    $user = costingUser(['Manage:CostingMethod']);
    postCostingPurchase($user, '1000.00000000', '100.00000000');
    $sale = postCostingSale($user, '200.00000000', '125.00000000');
    $snapshotProfit = $sale->profit_amount;

    $sale->update(['total_gross_profit' => '999999.99', 'total_inventory_cost' => '1.00']);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);

    $report = app(FinancialReportService::class)->profitAndLoss(now()->startOfDay(), now()->endOfDay());
    $filtered = app(ReportDataBuilder::class)->build([
        'report' => 'sales',
        'from' => today(),
        'to' => today(),
        'costing_method' => CostingMethod::Average->value,
    ]);

    expect($report['gross_profit'])->toBe($snapshotProfit)
        ->and($filtered['rows'])->toHaveCount(1)
        ->and($filtered['rows'][0][8])->toBe('Average')
        ->and($filtered['rows'][0][9])->toBe('posted');
});

test('dashboard shows the configured operational sell rate separately from inventory cost', function () {
    $user = costingUser(['Manage:SellRate', 'ViewProfit', 'ViewInternalCosts']);
    $this->actingAs($user);
    postCostingPurchase($user, '1000.00000000', '118.70000000');
    app(UpdateUsdSellRateAction::class)->execute('125.50000000', $user);

    expect(app(UsdSellRateService::class)->currentRate())->toBe('125.50000000')
        ->and(app(UsdSellRateService::class)->currentRate())->not->toBe(InventoryState::query()->sole()->weighted_average_cost)
        ->and(UsdSellRate::query()->sole()->rate)->toBe('125.50000000');

    Livewire::test(FinanceStats::class)
        ->assertSee('Current dollar sell rate')
        ->assertSee('৳ 125.50 / USD')
        ->assertSee('Current inventory cost')
        ->assertSee('৳ 118.7000');
});

test('dashboard sell rate falls back to the latest valid posted sale rate when unset', function () {
    $user = costingUser();
    postCostingPurchase($user, '1000.00000000', '100.00000000');
    postCostingSale($user, '100.00000000', '127.25000000');

    expect(app(UsdSellRateService::class)->configuredRate())->toBeNull()
        ->and(app(UsdSellRateService::class)->currentRate())->toBe('127.25000000')
        ->and(app(UsdSellRateService::class)->isFallback())->toBeTrue();
});

test('a sale cannot exceed authoritative inventory and rolls back all layer consumption', function () {
    $user = costingUser(['Manage:CostingMethod']);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);
    postCostingPurchase($user, '500.00000000', '110.00000000');

    expect(fn () => postCostingSale($user, '500.00000001'))->toThrow(LogicException::class);

    expect(InventoryState::query()->sole()->usd_balance)->toBe('500.00000000')
        ->and(app(UsdInventoryLayerService::class)->remainingQuantity())->toBe('500.00000000')
        ->and(SaleCostAllocation::query()->count())->toBe(0);
});

test('serialized competing layer sales cannot consume the same inventory twice', function () {
    $user = costingUser(['Manage:CostingMethod']);
    app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user);
    postCostingPurchase($user, '1000.00000000', '110.00000000');

    $first = postCostingSale($user, '700.00000000');

    expect(fn () => postCostingSale($user, '700.00000000'))->toThrow(LogicException::class)
        ->and($first->costAllocations()->sum('quantity_used'))->toEqual(700)
        ->and(app(UsdInventoryLayerService::class)->remainingQuantity())->toBe('300.00000000')
        ->and(InventoryState::query()->sole()->usd_balance)->toBe('300.00000000');
});

test('historical Average rows are backfilled by copying stored values without recalculation', function () {
    $sale = Sale::factory()->create([
        'status' => 'posted',
        'total_usd' => '321.12345678',
        'total_bdt_revenue' => '40140.43',
        'total_inventory_cost' => '37731.19',
        'total_gross_profit' => '2409.24',
        'posted_at' => Carbon::parse('2026-08-25 10:30:00'),
    ]);
    $platform = Platform::factory()->create();
    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => '321.12345678',
        'selling_rate' => '125.00000000',
        'weighted_cost_rate' => '117.43750000',
        'revenue' => '40140.43',
        'inventory_cost' => '37731.19',
        'gross_profit' => '2409.24',
    ]);

    $migration = require database_path('migrations/2026_08_26_212951_backfill_historical_sale_costing_snapshots.php');
    $migration->up();
    $sale->refresh();

    expect($sale->costing_method)->toBe(CostingMethod::Average)
        ->and($sale->cost_amount)->toBe('37731.19')
        ->and($sale->sell_amount)->toBe('40140.43')
        ->and($sale->profit_amount)->toBe('2409.24')
        ->and($sale->total_inventory_cost)->toBe('37731.19')
        ->and($sale->total_gross_profit)->toBe('2409.24')
        ->and($sale->lines()->sole()->costing_method)->toBe(CostingMethod::Average);
});

test('financial arithmetic uses fixed precision and half-up rounding', function () {
    expect(Money::amount('0.10000000', '1.25000000'))->toBe('0.13')
        ->and(Money::amount('0.10000000', '1.24999999'))->toBe('0.12')
        ->and(Money::rateFor('235000.00', '2000.00000000'))->toBe('117.50000000')
        ->and(Money::addUsd('0.12345678', '0.87654322'))->toBe('1.00000000');
});

test('only authorized administrators can change costing method and operational sell rate', function () {
    $user = costingUser();

    expect(fn () => app(ChangeCostingMethodAction::class)->execute(CostingMethod::Fifo, $user))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateUsdSellRateAction::class)->execute('125.00000000', $user))
        ->toThrow(AuthorizationException::class);
});
