<?php

declare(strict_types=1);

use App\Actions\PostSaleAction;
use App\Actions\PostUsdPurchaseAction;
use App\Actions\RecordPayrollPaymentAction;
use App\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use App\Filament\Admin\Resources\Employees\Pages\ListEmployees;
use App\Filament\Admin\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Admin\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Admin\Resources\Payrolls\Pages\ListPayrolls;
use App\Filament\Admin\Resources\Platforms\Pages\ListPlatforms;
use App\Filament\Admin\Resources\Sales\Pages\ListSales;
use App\Filament\Admin\Resources\UsdPurchases\Pages\ListUsdPurchases;
use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payroll;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Setting;
use App\Models\UsdPurchase;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Filament\Actions\ActionGroup;
use Filament\Tables\Enums\RecordActionsPosition;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

function actionsOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');

    return $user;
}

function actionsStock(User $user, string $quantity = '100000'): void
{
    app(PostUsdPurchaseAction::class)->execute(
        UsdPurchase::factory()->create(['usd_quantity' => $quantity, 'purchase_rate' => '100']),
        $user,
    );
}

function actionsDraftSale(User $user, Customer $customer, Platform $platform, string $quantity = '10'): Sale
{
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'created_by' => $user->id, 'status' => 'draft']);

    CustomerPlatform::query()->firstOrCreate(
        ['customer_id' => $customer->id, 'platform_id' => $platform->id],
        ['current_selling_rate' => '120'],
    );

    SaleLine::query()->create([
        'sale_id' => $sale->id,
        'platform_id' => $platform->id,
        'usd_quantity' => $quantity,
        'selling_rate' => '120',
        'weighted_cost_rate' => '0',
        'revenue' => '0',
        'inventory_cost' => '0',
        'gross_profit' => '0',
    ]);

    return $sale->refresh();
}

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
});

test('every table puts its record actions in one group before the columns', function () {
    $user = actionsOwner();
    $this->actingAs($user);

    $pages = [
        ListSales::class, ListUsdPurchases::class, ListExpenses::class, ListPayrolls::class,
        ListCustomers::class, ListEmployees::class, ListPlatforms::class, ListExpenseCategories::class,
        App\Filament\Admin\Resources\Invoices\Pages\ListInvoices::class,
        App\Filament\Admin\Resources\InventoryTransactions\Pages\ListInventoryTransactions::class,
        App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs::class,
        App\Filament\Admin\Resources\Users\Pages\ListUsers::class,
    ];

    foreach ($pages as $page) {
        $table = Livewire::test($page)->instance()->getTable();
        $actions = $table->getRecordActions();

        expect($actions)->toHaveCount(1, $page)
            ->and($actions[0])->toBeInstanceOf(ActionGroup::class)
            ->and($table->getRecordActionsPosition())->toBe(RecordActionsPosition::BeforeColumns, $page);
    }
});

test('bulk posting sales only touches drafts', function () {
    $user = actionsOwner();
    $this->actingAs($user);
    actionsStock($user);
    Setting::query()->create(['key' => 'sale_approval_required', 'value' => '0']);

    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();

    $draftOne = actionsDraftSale($user, $customer, $platform);
    $draftTwo = actionsDraftSale($user, $customer, $platform);
    $posted = app(PostSaleAction::class)->execute(actionsDraftSale($user, $customer, $platform), $user);

    Livewire::test(ListSales::class)
        ->callTableBulkAction('bulkSubmit', [$draftOne->id, $draftTwo->id, $posted->id])
        ->assertHasNoActionErrors();

    expect($draftOne->refresh()->status)->toBe('posted')
        ->and($draftTwo->refresh()->status)->toBe('posted')
        ->and($posted->refresh()->status)->toBe('posted')
        ->and(Sale::query()->where('status', 'posted')->count())->toBe(3);
});

test('bulk deleting sales refuses anything that is not a draft', function () {
    $user = actionsOwner();
    $this->actingAs($user);
    actionsStock($user);

    $customer = Customer::factory()->create();
    $platform = Platform::factory()->create();

    $draft = actionsDraftSale($user, $customer, $platform);
    $posted = app(PostSaleAction::class)->execute(actionsDraftSale($user, $customer, $platform), $user);

    Livewire::test(ListSales::class)
        ->callTableBulkAction('bulkDeleteDrafts', [$draft->id, $posted->id]);

    expect(Sale::query()->whereKey($draft->id)->exists())->toBeFalse()
        ->and(Sale::query()->whereKey($posted->id)->exists())->toBeTrue();
});

test('bulk posting purchases skips the ones already posted', function () {
    $user = actionsOwner();
    $this->actingAs($user);

    $draft = UsdPurchase::factory()->create(['usd_quantity' => '100', 'purchase_rate' => '110', 'status' => 'draft']);
    $posted = app(PostUsdPurchaseAction::class)->execute(
        UsdPurchase::factory()->create(['usd_quantity' => '50', 'purchase_rate' => '105']),
        $user,
    );
    $postedAt = $posted->posted_at;

    Livewire::test(ListUsdPurchases::class)
        ->callTableBulkAction('bulkPost', [$draft->id, $posted->id]);

    expect($draft->refresh()->status)->toBe('posted')
        ->and($posted->refresh()->posted_at->equalTo($postedAt))->toBeTrue();
});

test('bulk settling payroll pays exactly the outstanding balance', function () {
    $user = actionsOwner();
    $this->actingAs($user);

    $employee = Employee::factory()->create(['current_salary' => '2000']);
    $open = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-BULK-001',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '2000', 'total_salary_expense' => '2000',
        'paid_amount' => '500', 'due_amount' => '1500', 'status' => 'partially_paid',
    ]);
    $settled = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-BULK-002',
        'employee_id' => Employee::factory()->create()->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '1000', 'total_salary_expense' => '1000',
        'paid_amount' => '1000', 'due_amount' => '0', 'status' => 'paid',
    ]);

    Livewire::test(ListPayrolls::class)
        ->callTableBulkAction('bulkSettle', [$open->id, $settled->id], ['payment_date' => today()->toDateString(), 'method' => 'Bank']);

    expect($open->refresh()->paid_amount)->toBe('2000.00')
        ->and($open->due_amount)->toBe('0.00')
        ->and($open->status)->toBe('paid')
        ->and($open->payments()->count())->toBe(1)
        ->and($settled->refresh()->payments()->count())->toBe(0);
});

test('bulk deleting payroll refuses runs that already have payments', function () {
    $user = actionsOwner();
    $this->actingAs($user);

    $employee = Employee::factory()->create(['current_salary' => '1000']);
    $clean = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-BULK-003',
        'employee_id' => $employee->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '1000', 'total_salary_expense' => '1000',
        'paid_amount' => '0', 'due_amount' => '1000', 'status' => 'unpaid',
    ]);
    $paid = Payroll::query()->create([
        'payroll_number' => 'PAYROLL-BULK-004',
        'employee_id' => Employee::factory()->create()->id,
        'payroll_month' => today()->startOfMonth()->toDateString(),
        'base_salary' => '1000', 'total_salary_expense' => '1000',
        'paid_amount' => '0', 'due_amount' => '1000', 'status' => 'unpaid',
    ]);
    app(RecordPayrollPaymentAction::class)->execute($paid, '400', $user, today()->toDateString());

    Livewire::test(ListPayrolls::class)
        ->callTableBulkAction('bulkDelete', [$clean->id, $paid->id]);

    expect(Payroll::query()->whereKey($clean->id)->exists())->toBeFalse()
        ->and(Payroll::query()->whereKey($paid->id)->exists())->toBeTrue();
});

test('bulk delete keeps customers, platforms and categories that are still referenced', function () {
    $user = actionsOwner();
    $this->actingAs($user);
    actionsStock($user);

    $tradingCustomer = Customer::factory()->create();
    $freshCustomer = Customer::factory()->create();
    $usedPlatform = Platform::factory()->create();
    $freePlatform = Platform::factory()->create();

    app(PostSaleAction::class)->execute(actionsDraftSale($user, $tradingCustomer, $usedPlatform), $user);

    $usedCategory = ExpenseCategory::query()->create(['name' => 'Rent']);
    $freeCategory = ExpenseCategory::query()->create(['name' => 'Unused']);
    Expense::query()->create([
        'expense_number' => 'EXP-BULK-001',
        'expense_date' => today()->toDateString(),
        'expense_category_id' => $usedCategory->id,
        'amount' => '100',
        'description' => 'Rent',
    ]);

    Livewire::test(ListCustomers::class)
        ->callTableBulkAction('bulkDelete', [$tradingCustomer->id, $freshCustomer->id]);
    Livewire::test(ListPlatforms::class)
        ->callTableBulkAction('bulkDelete', [$usedPlatform->id, $freePlatform->id]);
    Livewire::test(ListExpenseCategories::class)
        ->callTableBulkAction('bulkDelete', [$usedCategory->id, $freeCategory->id]);

    expect(Customer::query()->whereKey($tradingCustomer->id)->exists())->toBeTrue()
        ->and(Customer::query()->whereKey($freshCustomer->id)->exists())->toBeFalse()
        ->and(Platform::query()->whereKey($usedPlatform->id)->exists())->toBeTrue()
        ->and(Platform::query()->whereKey($freePlatform->id)->exists())->toBeFalse()
        ->and(ExpenseCategory::query()->whereKey($usedCategory->id)->exists())->toBeTrue()
        ->and(ExpenseCategory::query()->whereKey($freeCategory->id)->exists())->toBeFalse();
});

test('bulk activation flips only the records that need it', function () {
    $user = actionsOwner();
    $this->actingAs($user);

    $active = Customer::factory()->create(['is_active' => true]);
    $inactive = Customer::factory()->create(['is_active' => false]);

    Livewire::test(ListCustomers::class)
        ->callTableBulkAction('bulkActivate', [$active->id, $inactive->id]);

    expect($inactive->refresh()->is_active)->toBeTrue()
        ->and($active->refresh()->is_active)->toBeTrue();

    Livewire::test(ListCustomers::class)
        ->callTableBulkAction('bulkDeactivate', [$active->id, $inactive->id]);

    expect($inactive->refresh()->is_active)->toBeFalse()
        ->and($active->refresh()->is_active)->toBeFalse();
});

test('bulk recategorising expenses records the move', function () {
    $user = actionsOwner();
    $this->actingAs($user);

    $from = ExpenseCategory::query()->create(['name' => 'Office']);
    $to = ExpenseCategory::query()->create(['name' => 'Marketing']);

    $expense = Expense::query()->create([
        'expense_number' => 'EXP-MOVE-001',
        'expense_date' => today()->toDateString(),
        'expense_category_id' => $from->id,
        'amount' => '250',
        'description' => 'Ad spend',
    ]);

    Livewire::test(ListExpenses::class)
        ->callTableBulkAction('bulkRecategorise', [$expense->id], ['expense_category_id' => $to->id]);

    expect($expense->refresh()->expense_category_id)->toBe($to->id)
        ->and(App\Models\AuditLog::query()->where('action', 'expense_recategorised')->count())->toBe(1);
});

test('a user without the permission never sees the bulk action', function () {
    $this->seed(AuthorizationSeeder::class);
    $employee = User::factory()->create();
    $employee->assignRole('employee');
    $this->actingAs($employee);

    expect($employee->can('Reverse:Sale'))->toBeFalse();

    Livewire::test(ListSales::class)
        ->assertTableBulkActionHidden('bulkReverse')
        ->assertTableBulkActionVisible('bulkSubmit');
});

test('bulk deleting users cannot remove your own account', function () {
    $user = actionsOwner();
    $this->actingAs($user);

    $other = User::factory()->create();
    $other->assignRole('employee');

    Livewire::test(App\Filament\Admin\Resources\Users\Pages\ListUsers::class)
        ->callTableBulkAction('bulkDelete', [$user->id, $other->id]);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($other->id)->exists())->toBeFalse();
});

test('links that answer with a file are kept out of spa navigation', function () {
    $user = actionsOwner();
    $this->actingAs($user);

    // Boot the panel so its SPA exceptions are registered with the view manager.
    $this->get('/sales')->assertOk();

    $documentUrls = [
        route('invoices.pdf', 1),
        route('invoices.print', 1),
        route('reports.export', ['format' => 'pdf']),
        route('reports.export', ['format' => 'xlsx']),
        route('reports.export', ['format' => 'csv']),
    ];

    foreach ($documentUrls as $url) {
        // wire:navigate would make Livewire fetch the file and swap it into the document.
        expect(Filament\Support\Facades\FilamentView::hasSpaMode($url))->toBeFalse($url);
    }

    // Ordinary panel pages must still navigate as a single-page app.
    expect(Filament\Support\Facades\FilamentView::hasSpaMode(url('/sales')))->toBeTrue()
        ->and(Filament\Support\Facades\FilamentView::hasSpaMode(url('/invoices')))->toBeTrue();
});

test('the invoice pdf link is rendered as a plain anchor', function () {
    $user = actionsOwner();
    $this->actingAs($user);
    $this->get('/sales')->assertOk();

    foreach ([route('invoices.pdf', 1), route('reports.export', ['format' => 'pdf'])] as $url) {
        $anchor = (string) Filament\Support\generate_href_html($url);

        // Any of these hands the response to Livewire instead of the browser.
        expect($anchor)->toContain('href=')
            ->and($anchor)->not->toContain('wire:navigate', $url)
            ->and($anchor)->not->toContain('Alpine.navigate', $url);
    }

    // A normal panel page keeps its single-page navigation.
    expect((string) Filament\Support\generate_href_html(url('/sales')))->toContain('wire:navigate');
});
