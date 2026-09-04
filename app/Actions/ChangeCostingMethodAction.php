<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CostingMethodChange;
use App\Models\InventoryState;
use App\Models\User;
use App\Services\UsdCostingService;
use App\Services\UsdInventoryLayerService;
use App\Support\AuditLogger;
use App\Support\CostingMethod;
use App\Support\Money;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Switches the system-wide dollar costing method.
 *
 * Historical sales are never recalculated. The current inventory position is rebased into a
 * single opening layer so the incoming method starts from the real remaining balance and
 * book value instead of the original purchase quantities.
 */
final readonly class ChangeCostingMethodAction
{
    public function __construct(
        private SettingsRepository $settings,
        private UsdCostingService $costing,
        private UsdInventoryLayerService $layers,
        private AuditLogger $auditLogger,
    ) {}

    public function execute(CostingMethod $method, User $user, ?string $reason = null): CostingMethodChange
    {
        Gate::forUser($user)->authorize('Manage:CostingMethod');

        return DB::transaction(function () use ($method, $user, $reason): CostingMethodChange {
            $state = InventoryState::locked();
            $current = $this->costing->activeMethod();

            if ($current === $method) {
                throw new LogicException("The dollar costing method is already {$method->shortLabel()}.");
            }

            $balance = Money::usd($state->usd_balance);
            $value = Money::bdt($state->inventory_value_bdt);

            $openingLayer = null;

            if (! $this->layers->matchesPosition($balance, $value)) {
                $openingLayer = $this->layers->rebase(
                    usdQuantity: $balance,
                    inventoryValueBdt: $value,
                    user: $user,
                    note: "Opening inventory for {$method->shortLabel()} costing, carried over from {$current->shortLabel()}.",
                );
            }

            $this->settings->putMany([CostingMethod::SettingKey => $method->value]);

            $change = CostingMethodChange::query()->create([
                'old_method' => $current->value,
                'new_method' => $method->value,
                'changed_by' => $user->id,
                'changed_at' => now(),
                'usd_balance_snapshot' => $balance,
                'inventory_value_snapshot' => $value,
                'average_cost_snapshot' => Money::rate($state->weighted_average_cost),
                'opening_layer_id' => $openingLayer?->getKey(),
                'reason' => $reason,
            ]);

            $this->auditLogger->record(
                $user,
                'costing_method_changed',
                $change,
                oldValues: ['costing_method' => $current->value],
                newValues: [
                    'costing_method' => $method->value,
                    'usd_balance' => $balance,
                    'inventory_value_bdt' => $value,
                ],
                reason: $reason,
            );

            return $change;
        }, attempts: 5);
    }
}
