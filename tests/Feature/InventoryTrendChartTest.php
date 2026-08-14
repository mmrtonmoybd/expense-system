<?php

declare(strict_types=1);

use App\Filament\Admin\Widgets\InventoryTrendChart;
use App\Filament\Admin\Widgets\PayrollHealthChart;
use App\Models\Payroll;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

test('inventory trend chart handles dates without ledger entries', function () {
    Livewire::test(InventoryTrendChart::class)
        ->assertOk();
});

test('payroll health chart renders monthly salary paid and due data', function () {
    Payroll::factory()->create([
        'payroll_month' => today()->startOfMonth(),
        'total_salary_expense' => '30000.00',
        'paid_amount' => '20000.00',
        'due_amount' => '10000.00',
        'status' => 'partially_paid',
    ]);

    Livewire::test(PayrollHealthChart::class)
        ->assertOk();
});
