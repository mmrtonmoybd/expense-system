<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\Sale;
use App\Models\SaleCostAllocation;
use App\Models\User;
use App\Services\UsdInventoryLayerService;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class ReverseSaleAction
{
    public function __construct(private AuditLogger $auditLogger, private UsdInventoryLayerService $layers) {}

    public function execute(Sale $sale, User $user, string $reason): Sale
    {
        if (blank($reason)) {
            throw new LogicException('A reversal reason is required.');
        }

        return DB::transaction(function () use ($sale, $user, $reason): Sale {
            $sale = Sale::query()->with('invoice')->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== 'posted') {
                throw new LogicException('Only posted sales can be reversed.');
            }

            $state = InventoryState::locked();
            $allocations = SaleCostAllocation::query()->where('sale_id', $sale->id)->get();

            if ($allocations->isNotEmpty()) {
                // FIFO/LIFO recorded exactly which layers this sale consumed, so the dollars
                // go back where they came from and the acquisition order is unchanged.
                $restored = $this->layers->restore($allocations);
                $restoredQuantity = $restored['quantity'];
                $restoredCost = $restored['cost'];
                $restoredRate = Money::rateFor($restoredCost, $restoredQuantity);
            } else {
                // A moving-average sale has no layer allocations to reverse. Order does not
                // affect an average cost, so the position returns as a single layer.
                $restoredQuantity = Money::usd((string) $sale->total_usd);
                $restoredCost = Money::bdt($sale->total_inventory_cost);
                $restoredRate = Money::rateFor($restoredCost, $restoredQuantity);
                $this->layers->record(
                    originType: 'reversal',
                    usdQuantity: $restoredQuantity,
                    rate: $restoredRate,
                    costBdt: $restoredCost,
                    source: $sale,
                    user: $user,
                    note: "Reversal of sale {$sale->sale_number}: {$reason}",
                );
            }

            $newBalance = Money::addUsd($state->usd_balance, $restoredQuantity);
            $newValue = Money::addBdt($state->inventory_value_bdt, $restoredCost);
            $newRate = Money::rateFor($newValue, $newBalance);
            $state->update(['usd_balance' => $newBalance, 'inventory_value_bdt' => $newValue, 'weighted_average_cost' => $newRate]);
            $sale->update(['status' => 'reversed', 'note' => mb_trim(($sale->note ? $sale->note."\n" : '')."Reversal: {$reason}")]);
            $sale->invoice?->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancellation_reason' => $reason]);
            InventoryTransaction::query()->create(['transaction_type' => 'reversal', 'source_type' => Sale::class, 'source_id' => $sale->id, 'usd_in' => $restoredQuantity, 'cost_rate' => $restoredRate, 'bdt_value_in' => $restoredCost, 'resulting_usd_balance' => $newBalance, 'resulting_inventory_value_bdt' => $newValue, 'resulting_weighted_average_cost' => $newRate, 'created_by' => $user->id, 'occurred_at' => now(), 'note' => $reason]);
            $this->auditLogger->record($user, 'sale_reversed', $sale, reason: $reason);

            return $sale->refresh()->load('invoice');
        }, attempts: 5);
    }
}
