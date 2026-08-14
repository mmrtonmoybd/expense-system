<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Admin\Resources\Customers\Pages\EditCustomer;
use App\Filament\Admin\Resources\Customers\RelationManagers\CustomerPlatformsRelationManager;
use App\Filament\Admin\Resources\Payrolls\Pages\CreatePayroll;
use App\Filament\Admin\Resources\Sales\Pages\CreateSale;
use App\Filament\Admin\Resources\Sales\Pages\EditSale;
use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\CustomerPlatformRateHistory;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\InventoryTransactionPolicy;
use App\Policies\InvoicePolicy;
use Database\Seeders\AuthorizationSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

test('shield generated policies replace the custom permission trait and keep financial records read only', function () {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('owner');

    expect(file_exists(app_path('Policies/Concerns/HandlesResourcePermissions.php')))->toBeFalse()
        ->and(method_exists(InvoicePolicy::class, 'create'))->toBeFalse()
        ->and(method_exists(InventoryTransactionPolicy::class, 'create'))->toBeFalse()
        ->and(method_exists(AuditLogPolicy::class, 'create'))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('viewAny', Invoice::class))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('create', Invoice::class))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('create', InventoryTransaction::class))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('create', AuditLog::class))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', Expense::factory()->create()))->toBeFalse();
});

test('sale create hides override reason and never serializes authoritative financial snapshots', function () {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $sale = Sale::factory()->create(['status' => 'draft']);
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create([
        'customer_id' => $sale->customer_id,
        'platform_id' => $platform->id,
        'current_selling_rate' => '122',
    ]);
    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => '100',
        'selling_rate' => '122',
        'weighted_cost_rate' => '0',
        'revenue' => '0',
        'inventory_cost' => '0',
        'gross_profit' => '0',
    ]);

    $this->get(SaleResource::getUrl('create'))
        ->assertOk()
        ->assertDontSeeText('Override reason')
        ->assertDontSeeText('Cost / profit')
        ->assertDontSeeText('Revenue')
        ->assertSeeText('Sale total')
        ->assertDontSee('weighted_cost_rate', false)
        ->assertDontSee('inventory_cost', false)
        ->assertDontSee('gross_profit', false);

    $this->get(SaleResource::getUrl('edit', ['record' => $sale]))
        ->assertOk()
        ->assertSeeText('Override reason');

    Livewire::test(EditSale::class, ['record' => $sale->getRouteKey()])
        ->assertSchemaStateSet(function (array $state): array {
            expect(collect($state['lines'])->first())->not->toHaveKeys([
                'weighted_cost_rate',
                'revenue',
                'inventory_cost',
                'gross_profit',
                'is_rate_overridden',
                'rate_overridden_by',
            ]);

            return [];
        });
});

test('sale platform selection loads the customer platform rate', function () {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $customerPlatform = CustomerPlatform::factory()->create([
        'current_selling_rate' => '120.50',
    ]);
    $otherCustomerPlatform = CustomerPlatform::factory()->create([
        'current_selling_rate' => '124.75',
    ]);
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(CreateSale::class)
        ->set('data.customer_id', $customerPlatform->customer_id)
        ->set('data.lines.0.platform_id', $customerPlatform->platform_id)
        ->assertSet('data.lines.0.selling_rate', '120.50000000')
        ->set('data.customer_id', $otherCustomerPlatform->customer_id)
        ->assertSet('data.lines.0.platform_id', null)
        ->assertSet('data.lines.0.selling_rate', null)
        ->set('data.lines.0.platform_id', $otherCustomerPlatform->platform_id)
        ->assertSet('data.lines.0.selling_rate', '124.75000000');

    $undoRepeaterFake();
});

test('customer platforms are managed inline with rate history and no standalone navigation', function () {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();

    expect(class_exists('App\\Filament\\Admin\\Resources\\CustomerPlatforms\\CustomerPlatformResource'))->toBeFalse()
        ->and(Spatie\Permission\Models\Permission::query()->where('name', 'like', '%:CustomerPlatform')->exists())->toBeFalse();

    Livewire::test(CustomerPlatformsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'platform_id' => $platform->id,
            'current_selling_rate' => '122.50',
            'note' => 'Initial customer rate',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    $assignment = $customer->customerPlatforms()->sole();

    expect($assignment->current_selling_rate)->toBe('122.50000000')
        ->and(CustomerPlatformRateHistory::query()->whereBelongsTo($customer)->count())->toBe(1);

    Livewire::test(CustomerPlatformsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->callAction(TestAction::make(EditAction::class)->table($assignment), [
            'current_selling_rate' => '124.75',
            'note' => 'Updated after review',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($assignment->refresh()->current_selling_rate)->toBe('124.75000000')
        ->and($assignment->note)->toBe('Updated after review')
        ->and(CustomerPlatformRateHistory::query()->whereBelongsTo($customer)->count())->toBe(2);
});

test('new customer redirects to edit page for platform attachment', function () {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $component = Livewire::test(CreateCustomer::class)
        ->fillForm([
            'name' => 'New Customer',
            'phone' => '01700000000',
            'email' => null,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::query()->where('phone', '01700000000')->sole();

    $component->assertRedirect(CustomerResource::getUrl('edit', ['record' => $customer]));

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->assertSeeLivewire(CustomerPlatformsRelationManager::class);
});

test('owner can edit a pending sale while posted and reversed sales remain immutable', function () {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $pendingSale = Sale::factory()->create(['status' => 'pending', 'approval_status' => 'pending']);
    $postedSale = Sale::factory()->create(['status' => 'posted', 'approval_status' => 'approved']);
    $reversedSale = Sale::factory()->create(['status' => 'reversed', 'approval_status' => 'approved']);
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create([
        'customer_id' => $pendingSale->customer_id,
        'platform_id' => $platform->id,
        'current_selling_rate' => '122',
    ]);
    SaleLine::query()->create([
        'sale_id' => $pendingSale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => '100',
        'selling_rate' => '122',
        'weighted_cost_rate' => '0',
        'revenue' => '0',
        'inventory_cost' => '0',
        'gross_profit' => '0',
    ]);

    expect($owner->can('Update:Sale'))->toBeTrue()
        ->and(SaleResource::canEdit($pendingSale))->toBeTrue()
        ->and(SaleResource::canEdit($postedSale))->toBeFalse()
        ->and(SaleResource::canEdit($reversedSale))->toBeFalse();

    $this->get(SaleResource::getUrl('edit', ['record' => $pendingSale]))
        ->assertOk();

    Livewire::test(EditSale::class, ['record' => $pendingSale->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($pendingSale->refresh()->total_usd)->toBe('100.00000000')
        ->and($pendingSale->total_bdt_revenue)->toBe('12200.00')
        ->and($pendingSale->status)->toBe('pending');

    $this->get(SaleResource::getUrl('edit', ['record' => $postedSale]))
        ->assertForbidden();
});

test('payroll creation snapshots salary from the employee instead of browser state', function () {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('owner');
    $this->actingAs($owner);

    $employee = Employee::factory()->create(['current_salary' => '30000']);

    Livewire::test(CreatePayroll::class)
        ->fillForm([
            'employee_id' => $employee->id,
            'payroll_month' => '2026-08-01',
            'base_salary' => '1',
            'allowance' => '0',
            'bonus' => '0',
            'deduction' => '0',
            'adjustment' => '0',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($employee->payrolls()->sole()->base_salary)->toBe('30000.00')
        ->and($employee->payrolls()->sole()->total_salary_expense)->toBe('30000.00');
});
