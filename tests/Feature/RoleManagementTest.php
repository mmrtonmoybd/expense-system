<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Filament\Admin\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Filament\Admin\Resources\InventoryTransactions\InventoryTransactionResource;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\Payrolls\PayrollResource;
use App\Filament\Admin\Resources\Platforms\PlatformResource;
use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Filament\Admin\Resources\UsdPurchases\UsdPurchaseResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('owner can manage roles while admin cannot', function () {
    $this->seed(AuthorizationSeeder::class);

    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($owner->can('ViewAny:Role'))->toBeTrue()
        ->and($owner->can('Update:Role'))->toBeTrue()
        ->and($admin->can('ViewAny:Role'))->toBeFalse();

    $this->actingAs($owner)
        ->get(route('filament.admin.resources.shield.roles.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.shield.roles.index'))
        ->assertForbidden();
});

test('dashboard and office modules have organized navigation metadata', function () {
    expect(Dashboard::shouldRegisterNavigation())->toBeTrue()
        ->and(Dashboard::getNavigationIcon())->not->toBeNull()
        ->and(Dashboard::getNavigationGroup())->toBe('General');

    $resources = [
        SaleResource::class,
        InvoiceResource::class,
        CustomerResource::class,
        PlatformResource::class,
        UsdPurchaseResource::class,
        InventoryTransactionResource::class,
        ExpenseResource::class,
        ExpenseCategoryResource::class,
        EmployeeResource::class,
        PayrollResource::class,
        UserResource::class,
        AuditLogResource::class,
    ];

    foreach ($resources as $resource) {
        expect($resource::getNavigationGroup())->not->toBeNull()
            ->and($resource::getNavigationIcon())->not->toBeNull();
    }
});
