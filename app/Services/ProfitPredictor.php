<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryState;
use LogicException;

final class ProfitPredictor
{
    /** @return array<string, string> */
    public function calculate(string $usdQuantity, string $sellingRate): array
    {
        if (bccomp($usdQuantity, '0', 8) !== 1 || bccomp($sellingRate, '0', 8) !== 1) {
            throw new LogicException('USD quantity and selling rate must be greater than zero.');
        }
        $state = InventoryState::query()->firstOrCreate([], ['usd_balance' => 0, 'inventory_value_bdt' => 0, 'weighted_average_cost' => 0]);
        $revenue = bcmul($usdQuantity, $sellingRate, 2);
        $cost = bcmul($usdQuantity, (string) $state->weighted_average_cost, 2);
        $profit = bcsub($revenue, $cost, 2);
        $profitPerUsd = bcsub($sellingRate, (string) $state->weighted_average_cost, 8);
        $margin = bccomp($revenue, '0', 2) === 1 ? bcmul(bcdiv($profit, $revenue, 8), '100', 2) : '0.00';

        return ['available_usd' => (string) $state->usd_balance, 'weighted_cost' => (string) $state->weighted_average_cost, 'selling_rate' => $sellingRate, 'usd_quantity' => $usdQuantity, 'revenue' => $revenue, 'inventory_cost' => $cost, 'gross_profit' => $profit, 'profit_per_usd' => $profitPerUsd, 'gross_margin' => $margin, 'remaining_usd' => bcsub((string) $state->usd_balance, $usdQuantity, 8)];
    }

    public function requiredRate(string $usdQuantity, string $desiredProfit): string
    {
        if (bccomp($usdQuantity, '0', 8) !== 1) {
            throw new LogicException('USD quantity must be greater than zero.');
        }
        $cost = (string) (InventoryState::query()->first()?->weighted_average_cost ?? '0');

        return bcadd($cost, bcdiv($desiredProfit, $usdQuantity, 8), 8);
    }
}
