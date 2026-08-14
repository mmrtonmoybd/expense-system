<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PostUsdPurchaseAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(UsdPurchase $purchase, User $user): UsdPurchase
    {
        return DB::transaction(function () use ($purchase, $user): UsdPurchase {
            $purchase = UsdPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status === 'posted') {
                throw new LogicException('Posted purchases cannot be posted again.');
            }

            $state = InventoryState::query()->lockForUpdate()->firstOrCreate([], [
                'usd_balance' => '0', 'inventory_value_bdt' => '0', 'weighted_average_cost' => '0',
            ]);
            $quantity = (string) $purchase->usd_quantity;
            $value = bcmul($quantity, (string) $purchase->purchase_rate, 2);
            $newBalance = bcadd((string) $state->usd_balance, $quantity, 8);
            $newValue = bcadd((string) $state->inventory_value_bdt, $value, 2);
            $newRate = bcdiv($newValue, $newBalance, 8);

            $purchase->update(['bdt_total' => $value, 'status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);
            $state->update(['usd_balance' => $newBalance, 'inventory_value_bdt' => $newValue, 'weighted_average_cost' => $newRate]);
            InventoryTransaction::query()->create(['transaction_type' => 'purchase', 'source_type' => UsdPurchase::class, 'source_id' => $purchase->id, 'usd_in' => $quantity, 'cost_rate' => $purchase->purchase_rate, 'bdt_value_in' => $value, 'resulting_usd_balance' => $newBalance, 'resulting_inventory_value_bdt' => $newValue, 'resulting_weighted_average_cost' => $newRate, 'created_by' => $user->id, 'occurred_at' => now(), 'note' => $purchase->note]);
            $this->auditLogger->record($user, 'purchase_posted', $purchase, newValues: ['usd_quantity' => $quantity, 'bdt_total' => $value, 'weighted_average_cost' => $newRate]);

            return $purchase->refresh();
        }, attempts: 5);
    }
}
