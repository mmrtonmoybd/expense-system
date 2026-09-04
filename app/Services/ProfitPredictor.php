<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryState;
use App\Support\Money;
use LogicException;

final class ProfitPredictor
{
    /** @return array<string, string> */
    public function calculate(string $usdQuantity, string $sellingRate): array
    {
        if (bccomp($usdQuantity, '0', 8) !== 1 || bccomp($sellingRate, '0', 8) !== 1) {
            throw new LogicException('USD quantity and selling rate must be greater than zero.');
        }
        $state = InventoryState::current();
        $availableUsd = Money::usd((string) ($state?->usd_balance ?? '0'));
        $weightedCost = Money::rate((string) ($state?->weighted_average_cost ?? '0'));
        $revenue = Money::amount($usdQuantity, $sellingRate);
        $cost = Money::amount($usdQuantity, $weightedCost);
        $profit = Money::subBdt($revenue, $cost);
        $profitPerUsd = bcsub($sellingRate, $weightedCost, Money::RateScale);
        $margin = Money::isZero($revenue, Money::BdtScale)
            ? Money::bdt('0')
            : Money::bdt(bcmul(bcdiv($profit, $revenue, 16), '100', 16));

        return ['available_usd' => $availableUsd, 'weighted_cost' => $weightedCost, 'selling_rate' => $sellingRate, 'usd_quantity' => $usdQuantity, 'revenue' => $revenue, 'inventory_cost' => $cost, 'gross_profit' => $profit, 'profit_per_usd' => $profitPerUsd, 'gross_margin' => $margin, 'remaining_usd' => Money::subUsd($availableUsd, $usdQuantity)];
    }

    public function requiredRate(string $usdQuantity, string $desiredProfit): string
    {
        if (bccomp($usdQuantity, '0', 8) !== 1) {
            throw new LogicException('USD quantity must be greater than zero.');
        }
        $cost = Money::rate((string) (InventoryState::query()->value('weighted_average_cost') ?? '0'));

        return bcadd($cost, bcdiv($desiredProfit, $usdQuantity, Money::RateScale), Money::RateScale);
    }
}
