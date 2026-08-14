<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
final class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'phone' => fake()->unique()->numerify('01#########'), 'email' => fake()->safeEmail(), 'company_name' => fake()->company(), 'is_active' => true];
    }
}
