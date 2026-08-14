<?php

declare(strict_types=1);

use App\Actions\PostSaleAction;
use App\Actions\PostUsdPurchaseAction;
use App\Actions\ReverseSaleAction;
use App\Models\CustomerPlatform;
use App\Models\InventoryState;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\UsdPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('purchases calculate moving weighted average cost', function () {
    $user = User::factory()->create();
    $postPurchase = app(PostUsdPurchaseAction::class);
    $postPurchase->execute(UsdPurchase::factory()->create(['usd_quantity' => '1000.00000000', 'purchase_rate' => '111.00000000']), $user);
    $postPurchase->execute(UsdPurchase::factory()->create(['usd_quantity' => '500.00000000', 'purchase_rate' => '115.00000000']), $user);

    expect(InventoryState::query()->sole())->usd_balance->toBe('1500.00000000')
        ->inventory_value_bdt->toBe('168500.00')
        ->weighted_average_cost->toBe('112.33333333');
});

test('posted multi-platform sale preserves cost snapshots and generates invoice', function () {
    $user = User::factory()->create();
    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(), $user);
    $sale = Sale::factory()->create();
    $facebook = Platform::factory()->create();
    $tiktok = Platform::factory()->create();
    CustomerPlatform::query()->create(['customer_id' => $sale->customer_id, 'platform_id' => $facebook->id, 'current_selling_rate' => 122]);
    CustomerPlatform::query()->create(['customer_id' => $sale->customer_id, 'platform_id' => $tiktok->id, 'current_selling_rate' => 124]);
    SaleLine::query()->create(['sale_id' => $sale->id, 'platform_id' => $facebook->id, 'usd_quantity' => '300.00000000', 'selling_rate' => '122.00000000', 'weighted_cost_rate' => 0, 'revenue' => 0, 'inventory_cost' => 0, 'gross_profit' => 0]);
    SaleLine::query()->create(['sale_id' => $sale->id, 'platform_id' => $tiktok->id, 'usd_quantity' => '200.00000000', 'selling_rate' => '124.00000000', 'weighted_cost_rate' => 0, 'revenue' => 0, 'inventory_cost' => 0, 'gross_profit' => 0]);

    $sale = app(PostSaleAction::class)->execute($sale, $user);

    expect($sale->status)->toBe('posted')->and($sale->total_usd)->toBe('500.00000000')->and($sale->total_bdt_revenue)->toBe('61400.00')->and($sale->total_inventory_cost)->toBe('55500.00')->and($sale->total_gross_profit)->toBe('5900.00')->and($sale->invoice)->not->toBeNull();
    expect(InventoryState::query()->sole()->usd_balance)->toBe('500.00000000');
});

test('sale cannot oversell authoritative inventory', function () {
    $user = User::factory()->create();
    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '500.00000000']), $user);
    $sale = Sale::factory()->create();
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create(['customer_id' => $sale->customer_id, 'platform_id' => $platform->id, 'current_selling_rate' => 122]);
    SaleLine::query()->create(['sale_id' => $sale->id, 'platform_id' => $platform->id, 'usd_quantity' => '700.00000000', 'selling_rate' => '122.00000000', 'weighted_cost_rate' => 0, 'revenue' => 0, 'inventory_cost' => 0, 'gross_profit' => 0]);

    expect(fn () => app(PostSaleAction::class)->execute($sale, $user))->toThrow(LogicException::class, 'You only have 500.00000000 USD available. This sale requires 700.00000000.');
    expect(InventoryState::query()->sole()->usd_balance)->toBe('500.00000000');
});

test('sale reversal restores historical inventory cost and cancels invoice', function () {
    $user = User::factory()->create();
    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(), $user);
    $sale = Sale::factory()->create();
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create(['customer_id' => $sale->customer_id, 'platform_id' => $platform->id, 'current_selling_rate' => 122]);
    SaleLine::query()->create(['sale_id' => $sale->id, 'platform_id' => $platform->id, 'usd_quantity' => '300.00000000', 'selling_rate' => '122.00000000', 'weighted_cost_rate' => 0, 'revenue' => 0, 'inventory_cost' => 0, 'gross_profit' => 0]);
    $sale = app(PostSaleAction::class)->execute($sale, $user);

    $sale = app(ReverseSaleAction::class)->execute($sale, $user, 'Customer request');

    expect($sale->status)->toBe('reversed')->and($sale->invoice->status)->toBe('cancelled');
    expect(InventoryState::query()->sole()->usd_balance)->toBe('1000.00000000');
});
