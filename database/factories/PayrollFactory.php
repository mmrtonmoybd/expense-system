<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
final class PayrollFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_number' => 'PAYROLL-TEST-'.fake()->unique()->numerify('########'),
            'employee_id' => Employee::factory(),
            'payroll_month' => fake()->dateTimeBetween('-12 months', 'now')->format('Y-m-01'),
            'base_salary' => '30000.00',
            'allowance' => '0.00',
            'bonus' => '0.00',
            'deduction' => '0.00',
            'adjustment' => '0.00',
            'total_salary_expense' => '30000.00',
            'paid_amount' => '0.00',
            'due_amount' => '30000.00',
            'payment_date' => null,
            'status' => 'unpaid',
            'notes' => null,
        ];
    }
}
