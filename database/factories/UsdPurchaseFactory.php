<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UsdPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UsdPurchase> */
final class UsdPurchaseFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_number' => 'PUR-'.fake()->unique()->numerify('######'), 'purchase_date' => today(), 'usd_quantity' => '1000.00000000', 'purchase_rate' => '111.00000000', 'bdt_total' => '111000.00', 'status' => 'draft'];
    }
}
