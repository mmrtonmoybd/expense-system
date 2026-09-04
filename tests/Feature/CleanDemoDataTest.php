<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryLayer;
use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\TransactionSequence;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Services\UsdSellRateService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

test('it clears every business table and resets the ledger', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Sale::query()->count())->toBeGreaterThan(0)
        ->and(Invoice::query()->count())->toBeGreaterThan(0)
        ->and(InventoryTransaction::query()->count())->toBeGreaterThan(0)
        ->and(InventoryState::query()->sole()->usd_balance)->not->toBe('0.00000000');

    $this->artisan('demo:clean --force')->assertSuccessful();

    expect(Sale::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0)
        ->and(Customer::query()->count())->toBe(0)
        ->and(UsdPurchase::query()->count())->toBe(0)
        ->and(Expense::query()->count())->toBe(0)
        ->and(ExpenseCategory::query()->count())->toBe(0)
        ->and(Payroll::query()->count())->toBe(0)
        ->and(InventoryTransaction::query()->count())->toBe(0)
        ->and(InventoryLayer::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and(TransactionSequence::query()->count())->toBe(0);
});

test('the inventory position survives as a zeroed singleton', function () {
    $this->seed(DemoDataSeeder::class);

    $this->artisan('demo:clean --force')->assertSuccessful();

    // The row has a unique index and is created by migration, so it is zeroed, not deleted.
    $state = InventoryState::query()->sole();

    expect($state->usd_balance)->toBe('0.00000000')
        ->and($state->inventory_value_bdt)->toBe('0.00')
        ->and($state->weighted_average_cost)->toBe('0.00000000');
});

test('roles, permissions and branding settings are left alone', function () {
    $this->seed(DemoDataSeeder::class);
    Setting::query()->updateOrCreate(['key' => 'business_name'], ['value' => 'Ads City']);

    $roles = Role::query()->count();
    $permissions = Permission::query()->count();

    $this->artisan('demo:clean --force')->assertSuccessful();

    expect(Role::query()->count())->toBe($roles)
        ->and(Permission::query()->count())->toBe($permissions)
        ->and(Setting::query()->where('key', 'business_name')->value('value'))->toBe('Ads City')
        // Operational state tied to the wiped data does go.
        ->and(Setting::query()->where('key', 'demo_seeded_at')->exists())->toBeFalse()
        ->and(Setting::query()->where('key', UsdSellRateService::SettingKey)->exists())->toBeFalse();
});

test('the demo logins go but a real account keeps the panel usable', function () {
    $this->seed(DemoDataSeeder::class);
    $real = User::factory()->create(['email' => 'owner@adscity.test']);

    expect(User::query()->where('email', 'like', '%@demo.test')->count())->toBe(5);

    $this->artisan('demo:clean --force')->assertSuccessful();

    expect(User::query()->where('email', 'like', '%@demo.test')->count())->toBe(0)
        ->and(User::query()->whereKey($real->id)->exists())->toBeTrue();
});

test('demo logins are kept when they are the only accounts', function () {
    $this->seed(DemoDataSeeder::class);
    User::query()->where('email', 'not like', '%@demo.test')->delete();

    $this->artisan('demo:clean --force')
        ->expectsOutputToContain('would leave the panel with no users')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(5);
});

test('keep-users leaves the demo logins in place', function () {
    $this->seed(DemoDataSeeder::class);
    User::factory()->create(['email' => 'owner@adscity.test']);

    $this->artisan('demo:clean --force --keep-users')->assertSuccessful();

    expect(User::query()->where('email', 'like', '%@demo.test')->count())->toBe(5)
        ->and(Sale::query()->count())->toBe(0);
});

test('declining the prompt deletes nothing', function () {
    $this->seed(DemoDataSeeder::class);
    $sales = Sale::query()->count();

    $this->artisan('demo:clean')
        ->expectsConfirmation('Delete all business data and reset the inventory ledger to zero?', 'no')
        ->assertFailed();

    expect(Sale::query()->count())->toBe($sales)
        ->and(InventoryState::query()->sole()->usd_balance)->not->toBe('0.00000000');
});

test('it reports an empty database instead of doing work', function () {
    $this->artisan('demo:clean --force')
        ->expectsOutputToContain('no business data')
        ->assertSuccessful();
});

test('the demo seeder can run again afterwards', function () {
    $this->seed(DemoDataSeeder::class);
    $this->artisan('demo:clean --force')->assertSuccessful();

    // The seeder refuses to run twice; clearing its marker is part of a clean slate.
    $this->seed(DemoDataSeeder::class);

    expect(Sale::query()->count())->toBeGreaterThan(0)
        ->and(InventoryState::query()->sole()->usd_balance)->not->toBe('0.00000000');
});
