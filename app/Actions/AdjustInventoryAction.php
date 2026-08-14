<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AdjustInventoryAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(string $type, string $usdQuantity, User $user, string $reason): InventoryState
    {
        if (! in_array($type, ['positive_adjustment', 'negative_adjustment'], true) || bccomp($usdQuantity, '0', 8) !== 1 || blank($reason)) {
            throw new LogicException('Adjustment type, positive USD quantity, and reason are required.');
        }

        return DB::transaction(function () use ($type, $usdQuantity, $user, $reason): InventoryState {
            $state = InventoryState::query()->lockForUpdate()->firstOrCreate([], [
                'usd_balance' => '0',
                'inventory_value_bdt' => '0',
                'weighted_average_cost' => '0',
            ]);
            if ($type === 'negative_adjustment' && bccomp($usdQuantity, (string) $state->usd_balance, 8) === 1) {
                throw new LogicException("You only have {$state->usd_balance} USD available.");
            }

            $value = bcmul($usdQuantity, (string) $state->weighted_average_cost, 2);
            $newBalance = $type === 'positive_adjustment' ? bcadd((string) $state->usd_balance, $usdQuantity, 8) : bcsub((string) $state->usd_balance, $usdQuantity, 8);
            $newValue = $type === 'positive_adjustment' ? bcadd((string) $state->inventory_value_bdt, $value, 2) : bcsub((string) $state->inventory_value_bdt, $value, 2);
            $newRate = bccomp($newBalance, '0', 8) === 1 ? bcdiv($newValue, $newBalance, 8) : '0';
            $state->update(['usd_balance' => $newBalance, 'inventory_value_bdt' => $newValue, 'weighted_average_cost' => $newRate]);
            $ledger = InventoryTransaction::query()->create(['transaction_type' => $type, 'source_type' => InventoryState::class, 'source_id' => $state->id, 'usd_in' => $type === 'positive_adjustment' ? $usdQuantity : 0, 'usd_out' => $type === 'negative_adjustment' ? $usdQuantity : 0, 'cost_rate' => $state->weighted_average_cost, 'bdt_value_in' => $type === 'positive_adjustment' ? $value : 0, 'bdt_value_out' => $type === 'negative_adjustment' ? $value : 0, 'resulting_usd_balance' => $newBalance, 'resulting_inventory_value_bdt' => $newValue, 'resulting_weighted_average_cost' => $newRate, 'created_by' => $user->id, 'occurred_at' => now(), 'note' => $reason]);
            $this->auditLogger->record($user, 'inventory_adjusted', $ledger, newValues: ['type' => $type, 'usd_quantity' => $usdQuantity], reason: $reason);

            return $state->refresh();
        }, attempts: 5);
    }
}
