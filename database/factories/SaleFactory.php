<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
final class SaleFactory extends Factory
{
    public function definition(): array
    {
        return ['sale_number' => 'SAL-'.fake()->unique()->numerify('######'), 'sale_date' => today(), 'customer_id' => Customer::factory(), 'status' => 'draft', 'approval_status' => 'not_required'];
    }
}
