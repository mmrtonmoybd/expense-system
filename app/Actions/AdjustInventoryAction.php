<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\UsdCostingService;
use App\Services\UsdInventoryLayerService;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AdjustInventoryAction
{
    public function __construct(private AuditLogger $auditLogger, private UsdInventoryLayerService $layers, private UsdCostingService $costing) {}

    public function execute(string $type, string $usdQuantity, User $user, string $reason): InventoryState
    {
        if (! in_array($type, ['positive_adjustment', 'negative_adjustment'], true) || ! Money::isPositiveUsd($usdQuantity) || blank($reason)) {
            throw new LogicException('Adjustment type, positive USD quantity, and reason are required.');
        }

        return DB::transaction(function () use ($type, $usdQuantity, $user, $reason): InventoryState {
            $state = InventoryState::query()->lockForUpdate()->firstOrCreate([], [
                'usd_balance' => '0',
                'inventory_value_bdt' => '0',
                'weighted_average_cost' => '0',
            ]);
            if ($type === 'negative_adjustment' && Money::compareUsd($usdQuantity, $state->usd_balance) === 1) {
                throw new LogicException("You only have {$state->usd_balance} USD available.");
            }

            $rate = Money::rate($state->weighted_average_cost);
            $value = Money::amount($usdQuantity, $rate);
            $isIncrease = $type === 'positive_adjustment';

            if ($isIncrease) {
                $this->layers->record(
                    originType: 'positive_adjustment',
                    usdQuantity: $usdQuantity,
                    rate: $rate,
                    costBdt: $value,
                    source: $state,
                    user: $user,
                    note: $reason,
                );
            } else {
                $value = collect($this->layers->consume($usdQuantity, $this->costing->activeMethod()))->reduce(
                    fn (string $carry, array $allocation): string => Money::addBdt($carry, $allocation['cost']),
                    '0',
                );
            }

            $newBalance = $isIncrease ? Money::addUsd($state->usd_balance, $usdQuantity) : Money::subUsd($state->usd_balance, $usdQuantity);
            $newValue = Money::isZero($newBalance)
                ? Money::bdt('0')
                : ($isIncrease
                    ? Money::addBdt($state->inventory_value_bdt, $value)
                    : Money::subBdt($state->inventory_value_bdt, $value));
            $newRate = Money::isPositiveUsd($newBalance) ? Money::rateFor($newValue, $newBalance) : Money::rate('0');
            $state->update(['usd_balance' => $newBalance, 'inventory_value_bdt' => $newValue, 'weighted_average_cost' => $newRate]);
            $ledger = InventoryTransaction::query()->create(['transaction_type' => $type, 'source_type' => InventoryState::class, 'source_id' => $state->id, 'usd_in' => $isIncrease ? $usdQuantity : 0, 'usd_out' => $isIncrease ? 0 : $usdQuantity, 'cost_rate' => Money::rateFor($value, $usdQuantity), 'bdt_value_in' => $isIncrease ? $value : 0, 'bdt_value_out' => $isIncrease ? 0 : $value, 'resulting_usd_balance' => $newBalance, 'resulting_inventory_value_bdt' => $newValue, 'resulting_weighted_average_cost' => $newRate, 'created_by' => $user->id, 'occurred_at' => now(), 'note' => $reason]);
            $this->auditLogger->record($user, 'inventory_adjusted', $ledger, newValues: ['type' => $type, 'usd_quantity' => $usdQuantity], reason: $reason);

            return $state->refresh();
        }, attempts: 5);
    }
}
