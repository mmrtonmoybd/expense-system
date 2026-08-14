<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Employee;
use App\Models\InventoryState;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Sale;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('demo seeder creates a financially consistent multi-month workflow', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Customer::query()->count())->toBeGreaterThanOrEqual(15)
        ->and(Employee::query()->count())->toBeGreaterThanOrEqual(7)
        ->and(Payroll::query()->count())->toBeGreaterThanOrEqual(21)
        ->and(Sale::query()->where('status', 'posted')->count())->toBeGreaterThan(10)
        ->and(Sale::query()->where('status', 'pending')->count())->toBe(1)
        ->and(Sale::query()->where('status', 'rejected')->count())->toBe(1)
        ->and(Sale::query()->where('status', 'reversed')->count())->toBe(1)
        ->and(Invoice::query()->count())->toBeGreaterThan(10)
        ->and(bccomp((string) InventoryState::query()->sole()->usd_balance, '0', 8))->not->toBe(-1);
});
