<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
final class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => fake()->unique()->bothify('EMP-####'),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('01#########'),
            'email' => fake()->optional()->safeEmail(),
            'designation' => fake()->randomElement(['Accountant', 'Sales Executive', 'Office Manager']),
            'joining_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'current_salary' => fake()->numberBetween(20000, 80000),
            'is_active' => true,
            'user_id' => null,
            'notes' => null,
        ];
    }
}
