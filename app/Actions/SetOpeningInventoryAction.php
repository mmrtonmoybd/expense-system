<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\UsdInventoryLayerService;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class SetOpeningInventoryAction
{
    public function __construct(private AuditLogger $auditLogger, private UsdInventoryLayerService $layers) {}

    public function execute(string $usdQuantity, string $inventoryValueBdt, User $user, string $reason): InventoryState
    {
        if (! Money::isPositiveUsd($usdQuantity) || Money::compareBdt($inventoryValueBdt, '0') !== 1 || blank($reason)) {
            throw new LogicException('Opening USD, inventory value, and reason are required.');
        }

        return DB::transaction(function () use ($usdQuantity, $inventoryValueBdt, $user, $reason): InventoryState {
            $state = InventoryState::locked();
            if (InventoryTransaction::query()->exists() || ! Money::isZero($state->usd_balance)) {
                throw new LogicException('Opening inventory can only be configured before the first inventory transaction.');
            }

            $rate = Money::rateFor($inventoryValueBdt, $usdQuantity);
            $state->update([
                'usd_balance' => $usdQuantity,
                'inventory_value_bdt' => $inventoryValueBdt,
                'weighted_average_cost' => $rate,
            ]);
            $this->layers->record(
                originType: 'opening_balance',
                usdQuantity: $usdQuantity,
                rate: $rate,
                costBdt: $inventoryValueBdt,
                source: $state,
                user: $user,
                note: $reason,
            );
            $ledger = InventoryTransaction::query()->create([
                'transaction_type' => 'opening_balance',
                'source_type' => InventoryState::class,
                'source_id' => $state->id,
                'usd_in' => $usdQuantity,
                'cost_rate' => $rate,
                'bdt_value_in' => $inventoryValueBdt,
                'resulting_usd_balance' => $usdQuantity,
                'resulting_inventory_value_bdt' => $inventoryValueBdt,
                'resulting_weighted_average_cost' => $rate,
                'created_by' => $user->id,
                'occurred_at' => now(),
                'note' => $reason,
            ]);
            $this->auditLogger->record($user, 'inventory_opening_balance_set', $ledger, newValues: [
                'usd_quantity' => $usdQuantity,
                'inventory_value_bdt' => $inventoryValueBdt,
                'weighted_average_cost' => $rate,
            ], reason: $reason);

            return $state->refresh();
        }, attempts: 5);
    }
}
