<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Services\UsdInventoryLayerService;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PostUsdPurchaseAction
{
    public function __construct(private AuditLogger $auditLogger, private UsdInventoryLayerService $layers) {}

    public function execute(UsdPurchase $purchase, User $user): UsdPurchase
    {
        return DB::transaction(function () use ($purchase, $user): UsdPurchase {
            $purchase = UsdPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status === 'posted') {
                throw new LogicException('Posted purchases cannot be posted again.');
            }

            $state = InventoryState::locked();
            $quantity = Money::usd($purchase->usd_quantity);
            $value = Money::amount($quantity, $purchase->purchase_rate);
            $newBalance = Money::addUsd($state->usd_balance, $quantity);
            $newValue = Money::addBdt($state->inventory_value_bdt, $value);
            $newRate = Money::rateFor($newValue, $newBalance);

            $purchase->update(['bdt_total' => $value, 'status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);
            $state->update(['usd_balance' => $newBalance, 'inventory_value_bdt' => $newValue, 'weighted_average_cost' => $newRate]);
            $this->layers->record(
                originType: 'purchase',
                usdQuantity: $quantity,
                rate: (string) $purchase->purchase_rate,
                costBdt: $value,
                source: $purchase,
                usdPurchaseId: $purchase->id,
                user: $user,
                note: $purchase->note,
            );
            InventoryTransaction::query()->create(['transaction_type' => 'purchase', 'source_type' => UsdPurchase::class, 'source_id' => $purchase->id, 'usd_in' => $quantity, 'cost_rate' => $purchase->purchase_rate, 'bdt_value_in' => $value, 'resulting_usd_balance' => $newBalance, 'resulting_inventory_value_bdt' => $newValue, 'resulting_weighted_average_cost' => $newRate, 'created_by' => $user->id, 'occurred_at' => now(), 'note' => $purchase->note]);
            $this->auditLogger->record($user, 'purchase_posted', $purchase, newValues: ['usd_quantity' => $quantity, 'bdt_total' => $value, 'weighted_average_cost' => $newRate]);

            return $purchase->refresh();
        }, attempts: 5);
    }
}
