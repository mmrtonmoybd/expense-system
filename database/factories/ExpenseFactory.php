<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
final class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_number' => 'EXP-TEST-'.fake()->unique()->numerify('########'),
            'expense_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => fake()->numberBetween(500, 50000),
            'description' => fake()->sentence(),
            'vendor' => fake()->optional()->company(),
            'reference' => null,
            'attachment_path' => null,
            'note' => null,
            'created_by' => null,
        ];
    }
}
