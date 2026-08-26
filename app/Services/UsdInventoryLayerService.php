<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryLayer;
use App\Models\User;
use App\Support\CostingMethod;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Maintains the physical USD inventory layers.
 *
 * Layers are kept for every inflow regardless of the active costing method, so the sum of
 * their remaining quantities always equals the authoritative USD balance. That invariant is
 * what allows FIFO/LIFO to start from a correct position after a method change.
 */
final class UsdInventoryLayerService
{
    public function record(
        string $originType,
        string $usdQuantity,
        string $rate,
        string $costBdt,
        ?Model $source = null,
        ?int $usdPurchaseId = null,
        ?User $user = null,
        ?string $note = null,
    ): InventoryLayer {
        if (! Money::isPositiveUsd($usdQuantity)) {
            throw new LogicException('An inventory layer requires a positive USD quantity.');
        }

        return InventoryLayer::query()->create([
            'origin_type' => $originType,
            'source_type' => $source ? $source::class : null,
            'source_id' => $source?->getKey(),
            'usd_purchase_id' => $usdPurchaseId,
            'purchase_rate' => Money::rate($rate),
            'original_quantity' => Money::usd($usdQuantity),
            'remaining_quantity' => Money::usd($usdQuantity),
            'original_cost_bdt' => Money::bdt($costBdt),
            'remaining_cost_bdt' => Money::bdt($costBdt),
            'is_closed' => false,
            'acquired_at' => now(),
            'created_by' => $user?->id,
            'note' => $note,
        ]);
    }

    /**
     * Consume inventory in the order dictated by the costing method, locking the layers so
     * two concurrent sales can never consume the same quantity.
     *
     * @return array<int, array{layer:InventoryLayer,quantity:string,rate:string,cost:string}>
     */
    public function consume(string $usdQuantity, CostingMethod $method): array
    {
        $outstanding = Money::usd($usdQuantity);

        if (! Money::isPositiveUsd($outstanding)) {
            throw new LogicException('Inventory consumption requires a positive USD quantity.');
        }

        $layers = $this->lockOpenLayers($method);
        $consumed = [];

        foreach ($layers as $layer) {
            if (Money::isZero($outstanding)) {
                break;
            }

            $available = Money::usd($layer->remaining_quantity);

            if (! Money::isPositiveUsd($available)) {
                continue;
            }

            $take = Money::minUsd($available, $outstanding);
            $exhausted = Money::compareUsd($take, $available) === 0;
            $cost = $exhausted
                ? Money::bdt($layer->remaining_cost_bdt)
                : $this->partialCost($layer, $take);

            $layer->update([
                'remaining_quantity' => Money::subUsd($available, $take),
                'remaining_cost_bdt' => Money::subBdt($layer->remaining_cost_bdt, $cost),
                'is_closed' => $exhausted,
            ]);

            $consumed[] = [
                'layer' => $layer,
                'quantity' => $take,
                'rate' => Money::rateFor($cost, $take),
                'cost' => $cost,
            ];

            $outstanding = Money::subUsd($outstanding, $take);
        }

        if (! Money::isZero($outstanding)) {
            throw new LogicException("Inventory layers are short by {$outstanding} USD. The sale cannot be costed.");
        }

        return $consumed;
    }

    /** Total USD still held across all open layers. */
    public function remainingQuantity(): string
    {
        return InventoryLayer::query()
            ->open()
            ->pluck('remaining_quantity')
            ->reduce(fn (string $total, mixed $quantity): string => Money::addUsd($total, (string) $quantity), Money::usd('0'));
    }

    /** Total book value still held across all open layers. */
    public function remainingCost(): string
    {
        return InventoryLayer::query()
            ->open()
            ->pluck('remaining_cost_bdt')
            ->reduce(fn (string $total, mixed $cost): string => Money::addBdt($total, (string) $cost), Money::bdt('0'));
    }

    /** Book cost per remaining USD across all open layers. */
    public function bookCostRate(): string
    {
        $quantity = $this->remainingQuantity();

        return Money::isPositiveUsd($quantity) ? Money::rateFor($this->remainingCost(), $quantity) : Money::rate('0');
    }

    /** Whether open layers reconcile exactly to the authoritative inventory position. */
    public function matchesPosition(string $usdQuantity, string $inventoryValueBdt): bool
    {
        return Money::compareUsd($this->remainingQuantity(), $usdQuantity) === 0
            && Money::compareBdt($this->remainingCost(), $inventoryValueBdt) === 0;
    }

    /**
     * Replace every open layer with a single layer holding the supplied position. Used when
     * the costing method changes so the incoming method starts from an accurate, internally
     * consistent inventory position without touching historical profit.
     */
    public function rebase(string $usdQuantity, string $inventoryValueBdt, ?User $user = null, ?string $note = null): ?InventoryLayer
    {
        InventoryLayer::query()->open()->lockForUpdate()->get()->each(
            fn (InventoryLayer $layer): bool => $layer->update([
                'remaining_quantity' => '0',
                'remaining_cost_bdt' => '0',
                'is_closed' => true,
            ]),
        );

        if (! Money::isPositiveUsd($usdQuantity)) {
            return null;
        }

        return $this->record(
            originType: 'method_change_rebase',
            usdQuantity: $usdQuantity,
            rate: Money::rateFor($inventoryValueBdt, $usdQuantity),
            costBdt: $inventoryValueBdt,
            user: $user,
            note: $note,
        );
    }

    /**
     * Return the outflow layers in consumption order with a write lock held.
     *
     * @return Collection<int, InventoryLayer>
     */
    private function lockOpenLayers(CostingMethod $method): Collection
    {
        $direction = $method === CostingMethod::Lifo ? 'desc' : 'asc';

        return InventoryLayer::query()
            ->open()
            ->orderBy('acquired_at', $direction)
            ->orderBy('id', $direction)
            ->lockForUpdate()
            ->get();
    }

    /** Cost of a partial take, never exceeding the value still booked on the layer. */
    private function partialCost(InventoryLayer $layer, string $quantity): string
    {
        $cost = Money::amount($quantity, $layer->purchase_rate);
        $remaining = Money::bdt($layer->remaining_cost_bdt);

        return Money::compareBdt($cost, $remaining) === 1 ? $remaining : $cost;
    }
}
