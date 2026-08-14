<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\Sale;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class ReverseSaleAction
{
    public function __construct(private AuditLogger $auditLogger) {}

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

            $state = InventoryState::query()->lockForUpdate()->sole();
            $newBalance = bcadd((string) $state->usd_balance, (string) $sale->total_usd, 8);
            $newValue = bcadd((string) $state->inventory_value_bdt, (string) $sale->total_inventory_cost, 2);
            $newRate = bcdiv($newValue, $newBalance, 8);
            $state->update(['usd_balance' => $newBalance, 'inventory_value_bdt' => $newValue, 'weighted_average_cost' => $newRate]);
            $sale->update(['status' => 'reversed', 'note' => mb_trim(($sale->note ? $sale->note."\n" : '')."Reversal: {$reason}")]);
            $sale->invoice?->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancellation_reason' => $reason]);
            InventoryTransaction::query()->create(['transaction_type' => 'reversal', 'source_type' => Sale::class, 'source_id' => $sale->id, 'usd_in' => $sale->total_usd, 'cost_rate' => bcdiv((string) $sale->total_inventory_cost, (string) $sale->total_usd, 8), 'bdt_value_in' => $sale->total_inventory_cost, 'resulting_usd_balance' => $newBalance, 'resulting_inventory_value_bdt' => $newValue, 'resulting_weighted_average_cost' => $newRate, 'created_by' => $user->id, 'occurred_at' => now(), 'note' => $reason]);
            $this->auditLogger->record($user, 'sale_reversed', $sale, reason: $reason);

            return $sale->refresh()->load('invoice');
        }, attempts: 5);
    }
}
