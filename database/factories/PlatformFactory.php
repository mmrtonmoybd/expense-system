<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Platform> */
final class PlatformFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'short_name' => fake()->unique()->lexify('???'), 'is_active' => true, 'sort_order' => 0];
    }
}
