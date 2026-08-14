<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPlatform>
 */
final class CustomerPlatformFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'platform_id' => Platform::factory(),
            'current_selling_rate' => fake()->randomFloat(2, 118, 130),
            'note' => null,
        ];
    }
}
