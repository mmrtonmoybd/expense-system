<?php

declare(strict_types=1);

use App\Actions\PostUsdPurchaseAction;
use App\Actions\RecordPayrollPaymentAction;
use App\Actions\SetOpeningInventoryAction;
use App\Actions\SubmitSaleAction;
use App\Actions\UpdateCustomerPlatformRateAction;
use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\Payrolls\PayrollResource;
use App\Filament\Admin\Resources\Platforms\PlatformResource;
use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Filament\Admin\Resources\UsdPurchases\UsdPurchaseResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\Employee;
use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\Payroll;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Setting;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Services\ProfitPredictor;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

test('customer requires phone but email is optional', function () {
    $customer = Customer::query()->create([
        'name' => 'Phone Only Customer',
        'phone' => '01712345678',
        'email' => null,
    ]);

    expect($customer->email)->toBeNull()
        ->and(Customer::query()->whereNull('email')->count())->toBe(1);

    expect(fn () => Customer::query()->create(['name' => 'Missing Phone']))
        ->toThrow(QueryException::class);
});

test('rate changes preserve complete history', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    $customer = Customer::query()->create(['name' => 'Rate Customer', 'phone' => '01700000001']);
    $platform = Platform::factory()->create();
    $assignment = CustomerPlatform::query()->create([
        'customer_id' => $customer->id,
        'platform_id' => $platform->id,
        'current_selling_rate' => '121.00000000',
    ]);

    app(UpdateCustomerPlatformRateAction::class)->execute($assignment, '124.50000000', $user, 'Reviewed pricing');

    $history = $assignment->customer->rateHistories()->sole();
    expect($assignment->refresh()->current_selling_rate)->toBe('124.50000000')
        ->and($history->old_rate)->toBe('121.00000000')
        ->and($history->new_rate)->toBe('124.50000000')
        ->and($history->reason)->toBe('Reviewed pricing');
});

test('opening inventory is auditable and can only be configured once', function () {
    $user = User::factory()->create();
    $state = app(SetOpeningInventoryAction::class)->execute('500', '56000', $user, 'Imported opening inventory');

    expect($state->usd_balance)->toBe('500.00000000')
        ->and($state->inventory_value_bdt)->toBe('56000.00')
        ->and($state->weighted_average_cost)->toBe('112.00000000')
        ->and(InventoryTransaction::query()->sole()->transaction_type)->toBe('opening_balance');

    expect(fn () => app(SetOpeningInventoryAction::class)->execute('10', '1120', $user, 'Second opening'))
        ->toThrow(LogicException::class, 'Opening inventory can only be configured before the first inventory transaction.');
});

test('approval setting keeps pending sale outside inventory until approval', function () {
    $user = User::factory()->create();
    app(PostUsdPurchaseAction::class)->execute(UsdPurchase::factory()->create(['usd_quantity' => '1000']), $user);
    Setting::query()->create(['key' => 'sale_approval_required', 'value' => '1']);
    $sale = Sale::factory()->create(['status' => 'draft']);
    $platform = Platform::factory()->create();
    CustomerPlatform::query()->create(['customer_id' => $sale->customer_id, 'platform_id' => $platform->id, 'current_selling_rate' => 122]);
    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => '250',
        'selling_rate' => '122',
        'weighted_cost_rate' => 0,
        'revenue' => 0,
        'inventory_cost' => 0,
        'gross_profit' => 0,
    ]);

    $pending = app(SubmitSaleAction::class)->execute($sale, $user);
    expect($pending->status)->toBe('pending')
        ->and($pending->total_usd)->toBe('250.00000000')
        ->and($pending->total_bdt_revenue)->toBe('30500.00')
        ->and(InventoryState::query()->sole()->usd_balance)->toBe('1000.00000000');

    $pending->update(['status' => 'draft']);
    Setting::query()->where('key', 'sale_approval_required')->update(['value' => '0']);
    $posted = app(SubmitSaleAction::class)->execute($pending, $user);
    expect($posted->status)->toBe('posted')
        ->and(InventoryState::query()->sole()->usd_balance)->toBe('750.00000000');
});

test('payroll partial payments never duplicate salary expense', function () {
    $user = User::factory()->create();
    $employee = Employee::query()->create([
        'employee_code' => 'EMP-TEST',
        'name' => 'Test Employee',
        'phone' => '01800000001',
        'designation' => 'Tester',
        'joining_date' => today(),
        'current_salary' => '30000',
    ]);
    $payroll = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-TEST-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth(),
        'base_salary' => '30000',
        'total_salary_expense' => '30000',
        'paid_amount' => '0',
        'due_amount' => '30000',
        'status' => 'unpaid',
    ]);

    app(RecordPayrollPaymentAction::class)->execute($payroll, '20000', $user, today()->toDateString());
    app(RecordPayrollPaymentAction::class)->execute($payroll->refresh(), '10000', $user, today()->toDateString());

    expect($payroll->refresh()->total_salary_expense)->toBe('30000.00')
        ->and($payroll->paid_amount)->toBe('30000.00')
        ->and($payroll->due_amount)->toBe('0.00')
        ->and($payroll->payments()->sum('amount'))->toEqual(30000);
});

test('profit predictor calculates projection and target rate without transactions', function () {
    InventoryState::query()->create([
        'usd_balance' => '1000',
        'inventory_value_bdt' => '112000',
        'weighted_average_cost' => '112',
    ]);

    $result = app(ProfitPredictor::class)->calculate('200', '124');

    expect($result['revenue'])->toBe('24800.00')
        ->and($result['inventory_cost'])->toBe('22400.00')
        ->and($result['gross_profit'])->toBe('2400.00')
        ->and(app(ProfitPredictor::class)->requiredRate('200', '3000'))->toBe('127.00000000')
        ->and(InventoryState::query()->sole()->usd_balance)->toBe('1000.00000000');
});

test('authorized management can open every operational Filament module', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');

    $statuses = collect([
        '/', '/customers', '/platforms',
        '/usd-purchases', '/sales', '/pending-approvals', '/invoices',
        '/inventory-transactions', '/inventory-adjustment', '/expenses',
        '/expense-categories', '/employees', '/payrolls', '/reports',
        '/profit-predictor', '/settings', '/audit-logs', '/users',
        '/customers/create', '/platforms/create',
        '/usd-purchases/create', '/sales/create', '/expenses/create',
        '/expense-categories/create', '/employees/create', '/payrolls/create',
    ])->mapWithKeys(fn (string $path): array => [$path => $this->actingAs($user)->get($path)->status()]);

    expect($statuses->filter(fn (int $status): bool => $status !== 200)->all())->toBe([]);
});

test('pwa manifest is installable and financial service worker never queues posts', function () {
    $this->get(route('pwa.manifest'))
        ->assertOk()
        ->assertHeader('content-type', 'application/manifest+json')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', '/')
        ->assertJsonPath('related_applications.0.platform', 'webapp')
        ->assertJsonPath('prefer_related_applications', false)
        ->assertJsonCount(1, 'icons');

    $serviceWorker = file_get_contents(public_path('sw.js'));
    $installScript = file_get_contents(resource_path('js/pwa-install.js'));
    expect($serviceWorker)->toContain("event.request.method!=='GET'")
        ->and($serviceWorker)->not->toContain('sync')
        ->and($installScript)->toContain('beforeinstallprompt', 'appinstalled', 'getInstalledRelatedApps', "serviceWorker.register('/sw.js')")
        ->and(view('pwa.install-button')->render())->toContain('data-pwa-install-button', 'Install App');

    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('data-pwa-install-button', false)
        ->assertSee('js/app/pwa-install.js', false);
});

test('third party activity logger is removed without removing financial audit logs', function () {
    expect(Schema::hasTable('activity_log'))->toBeFalse()
        ->and(Schema::hasTable('audit_logs'))->toBeTrue()
        ->and(class_exists('JacobTims\\FilamentLogger\\FilamentLoggerPlugin'))->toBeFalse()
        ->and(class_exists('Spatie\\Activitylog\\Models\\Activity'))->toBeFalse();
});

test('important office records expose useful global search fields', function () {
    expect(CustomerResource::getGloballySearchableAttributes())->toBe(['name', 'phone', 'company_name', 'email'])
        ->and(InvoiceResource::getGloballySearchableAttributes())->toContain('invoice_number', 'sale.sale_number', 'sale.customer.phone')
        ->and(SaleResource::getGloballySearchableAttributes())->toContain('sale_number', 'invoice_number', 'customer.phone')
        ->and(UsdPurchaseResource::getGloballySearchableAttributes())->toContain('purchase_number', 'source', 'reference')
        ->and(ExpenseResource::getGloballySearchableAttributes())->toContain('expense_number', 'vendor', 'category.name')
        ->and(EmployeeResource::getGloballySearchableAttributes())->toContain('employee_code', 'name', 'phone')
        ->and(PayrollResource::getGloballySearchableAttributes())->toContain('payroll_number', 'employee.name', 'employee.phone')
        ->and(PlatformResource::getGloballySearchableAttributes())->toContain('name', 'short_name')
        ->and(UserResource::getGloballySearchableAttributes())->toBe(['name', 'email']);
});

test('customer global search finds phone numbers and shows the phone in its title', function () {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('owner');
    $this->actingAs($user);

    $customer = Customer::factory()->create([
        'name' => 'Searchable Trading',
        'phone' => '01799990000',
    ]);

    $result = CustomerResource::getGlobalSearchResults('01799990000')->sole();

    expect($result->title)->toBe("Searchable Trading ({$customer->phone})")
        ->and($result->details['Phone'])->toBe($customer->phone)
        ->and($result->url)->toContain("/customers/{$customer->id}");
});
