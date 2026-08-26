<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryState;
use App\Models\SaleLine;
use App\Support\CostingMethod;
use App\Support\Money;
use App\Support\SettingsRepository;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Calculates the cost of a USD sale using the costing method that is active right now.
 *
 * The result is a snapshot: callers persist it on the sale so the calculation can always be
 * reproduced, no matter how the system-wide setting changes afterwards.
 */
final readonly class UsdCostingService
{
    public function __construct(
        private SettingsRepository $settings,
        private UsdInventoryLayerService $layers,
    ) {}

    /** The costing method that new sales must use. */
    public function activeMethod(): CostingMethod
    {
        return CostingMethod::fromNullable($this->settings->get(CostingMethod::SettingKey, CostingMethod::default()->value));
    }

    /**
     * Cost a sale against the current inventory position and consume the inventory it uses.
     *
     * @param  Collection<int, SaleLine>  $lines
     * @return array{method:CostingMethod,cost_amount:string,cost_rate:string,lines:array<int, array{cost_rate:string,cost_amount:string,allocations:array<int, array{layer_id:int,usd_purchase_id:int|null,quantity:string,rate:string,cost:string}>}>}
     */
    public function costSale(Collection $lines, string $totalUsd, InventoryState $state): array
    {
        $method = $this->activeMethod();

        $costed = $method === CostingMethod::Average
            ? $this->costUsingAverage($lines, $totalUsd, $state)
            : $this->costUsingLayers($lines, $method);

        $costAmount = collect($costed)->reduce(
            fn (string $carry, array $line): string => Money::addBdt($carry, $line['cost_amount']),
            '0',
        );

        return [
            'method' => $method,
            'cost_amount' => $costAmount,
            'cost_rate' => $method === CostingMethod::Average
                ? Money::rate($state->weighted_average_cost)
                : Money::rateFor($costAmount, $totalUsd),
            'lines' => $costed,
        ];
    }

    /**
     * Moving weighted average: every line is charged the single average cost rate that the
     * inventory holds at this moment. Layers are still consumed so their remaining quantity
     * keeps matching the authoritative USD balance.
     *
     * @param  Collection<int, SaleLine>  $lines
     * @return array<int, array{cost_rate:string,cost_amount:string,allocations:array<int, array{layer_id:int,usd_purchase_id:int|null,quantity:string,rate:string,cost:string}>}>
     */
    private function costUsingAverage(Collection $lines, string $totalUsd, InventoryState $state): array
    {
        $rate = Money::rate($state->weighted_average_cost);
        $saleCost = Money::amount($totalUsd, $rate);
        $allocatedCost = Money::bdt('0');
        $costed = [];
        $lastLineKey = (int) $lines->last()?->getKey();

        foreach ($lines as $line) {
            $lineCost = (int) $line->getKey() === $lastLineKey
                ? Money::subBdt($saleCost, $allocatedCost)
                : Money::amount($line->usd_quantity, $rate);

            $costed[(int) $line->getKey()] = [
                'cost_rate' => $rate,
                'cost_amount' => $lineCost,
                'allocations' => [],
            ];
            $allocatedCost = Money::addBdt($allocatedCost, $lineCost);
        }

        $this->layers->consume($totalUsd, CostingMethod::Average);

        return $costed;
    }

    /**
     * FIFO/LIFO: each line automatically consumes whole inventory layers in acquisition
     * order. The operator never selects a batch.
     *
     * @param  Collection<int, SaleLine>  $lines
     * @return array<int, array{cost_rate:string,cost_amount:string,allocations:array<int, array{layer_id:int,usd_purchase_id:int|null,quantity:string,rate:string,cost:string}>}>
     */
    private function costUsingLayers(Collection $lines, CostingMethod $method): array
    {
        $costed = [];

        foreach ($lines as $line) {
            $consumed = $this->layers->consume((string) $line->usd_quantity, $method);
            $lineCost = '0';
            $allocations = [];

            foreach ($consumed as $allocation) {
                $lineCost = Money::addBdt($lineCost, $allocation['cost']);
                $allocations[] = [
                    'layer_id' => (int) $allocation['layer']->getKey(),
                    'usd_purchase_id' => $allocation['layer']->usd_purchase_id,
                    'quantity' => $allocation['quantity'],
                    'rate' => $allocation['rate'],
                    'cost' => $allocation['cost'],
                ];
            }

            if ($allocations === []) {
                throw new LogicException('No inventory layer was available to cost this sale line.');
            }

            $costed[(int) $line->getKey()] = [
                'cost_rate' => Money::rateFor($lineCost, (string) $line->usd_quantity),
                'cost_amount' => $lineCost,
                'allocations' => $allocations,
            ];
        }

        return $costed;
    }
}
