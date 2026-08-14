<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\PayrollPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPayment>
 */
final class PayrollPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_id' => Payroll::factory(),
            'amount' => '10000.00',
            'payment_date' => today(),
            'method' => 'Bank transfer',
            'reference' => null,
            'note' => null,
            'created_by' => null,
        ];
    }
}
