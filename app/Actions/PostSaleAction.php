<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CustomerPlatform;
use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\SaleCostAllocation;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\UsdCostingService;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PostSaleAction
{
    public function __construct(
        private AuditLogger $auditLogger,
        private SettingsRepository $settings,
        private UsdCostingService $costing,
    ) {}

    public function execute(Sale $sale, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $user): Sale {
            $sale = Sale::query()->with('lines')->lockForUpdate()->findOrFail($sale->id);

            if (in_array($sale->status, ['posted', 'reversed'], true)) {
                throw new LogicException('Only unposted sales can be posted.');
            }

            $totalUsd = '0';
            foreach ($sale->lines as $line) {
                if (! Money::isPositiveUsd($line->usd_quantity) || ! Money::isPositiveUsd($line->selling_rate)) {
                    throw new LogicException('Every sale line requires positive USD quantity and selling rate.');
                }
                $assignmentExists = CustomerPlatform::query()
                    ->where('customer_id', $sale->customer_id)
                    ->where('platform_id', $line->platform_id)
                    ->exists();
                if (! $assignmentExists) {
                    throw new LogicException('Every platform line must be assigned to the selected customer.');
                }
                $totalUsd = Money::addUsd($totalUsd, $line->usd_quantity);
            }

            $state = InventoryState::query()->lockForUpdate()->firstOrCreate([], [
                'usd_balance' => '0', 'inventory_value_bdt' => '0', 'weighted_average_cost' => '0',
            ]);
            if (Money::compareUsd($totalUsd, $state->usd_balance) === 1) {
                throw new LogicException("You only have {$state->usd_balance} USD available. This sale requires {$totalUsd}.");
            }

            $costing = $this->costing->costSale($sale->lines, $totalUsd, $state);
            $method = $costing['method'];
            $revenue = '0';
            $cost = '0';

            foreach ($sale->lines as $line) {
                $lineCosting = $costing['lines'][(int) $line->getKey()];
                $lineRevenue = Money::amount($line->usd_quantity, $line->selling_rate);
                $lineCost = $lineCosting['cost_amount'];
                $line->update([
                    'costing_method' => $method,
                    'weighted_cost_rate' => $lineCosting['cost_rate'],
                    'revenue' => $lineRevenue,
                    'inventory_cost' => $lineCost,
                    'gross_profit' => Money::subBdt($lineRevenue, $lineCost),
                ]);
                $this->storeAllocations($sale, $line, $method->value, $lineCosting['allocations']);
                $revenue = Money::addBdt($revenue, $lineRevenue);
                $cost = Money::addBdt($cost, $lineCost);
            }

            $newBalance = Money::subUsd($state->usd_balance, $totalUsd);
            $newValue = Money::isZero($newBalance)
                ? Money::bdt('0')
                : Money::subBdt($state->inventory_value_bdt, $cost);
            $newRate = Money::isPositiveUsd($newBalance) ? Money::rateFor($newValue, $newBalance) : Money::rate('0');
            $costRate = $costing['cost_rate'];
            $profit = Money::subBdt($revenue, $cost);
            $invoiceNumber = $sale->invoice_number ?: $this->invoiceNumber($sale);
            $sale->update([
                'invoice_number' => $invoiceNumber,
                'total_usd' => $totalUsd,
                'total_bdt_revenue' => $revenue,
                'total_inventory_cost' => $cost,
                'total_gross_profit' => $profit,
                'costing_method' => $method,
                'cost_rate' => $costRate,
                'cost_amount' => $cost,
                'sell_rate' => Money::rateFor($revenue, $totalUsd),
                'sell_amount' => $revenue,
                'profit_amount' => $profit,
                'costing_calculated_at' => now(),
                'status' => 'posted',
                'approval_status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'posted_at' => now(),
            ]);
            $state->update(['usd_balance' => $newBalance, 'inventory_value_bdt' => $newValue, 'weighted_average_cost' => $newRate]);
            InventoryTransaction::query()->create(['transaction_type' => 'sale', 'source_type' => Sale::class, 'source_id' => $sale->id, 'usd_out' => $totalUsd, 'cost_rate' => $costRate, 'bdt_value_out' => $cost, 'resulting_usd_balance' => $newBalance, 'resulting_inventory_value_bdt' => $newValue, 'resulting_weighted_average_cost' => $newRate, 'created_by' => $user->id, 'occurred_at' => now(), 'note' => $sale->note]);
            Invoice::query()->firstOrCreate(['sale_id' => $sale->id], ['invoice_number' => $invoiceNumber, 'invoice_date' => $sale->sale_date, 'status' => 'issued']);
            $this->auditLogger->record($user, 'sale_posted', $sale, newValues: ['total_usd' => $totalUsd, 'revenue' => $revenue, 'inventory_cost' => $cost, 'costing_method' => $method->value, 'cost_rate' => $costRate]);

            return $sale->refresh()->load('lines', 'invoice');
        }, attempts: 5);
    }

    /**
     * Persist the inventory layers a FIFO/LIFO line consumed so the cost stays auditable.
     *
     * @param  array<int, array{layer_id:int,usd_purchase_id:int|null,quantity:string,rate:string,cost:string}>  $allocations
     */
    private function storeAllocations(Sale $sale, SaleLine $line, string $method, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            SaleCostAllocation::query()->create([
                'sale_id' => $sale->id,
                'sale_line_id' => $line->id,
                'inventory_layer_id' => $allocation['layer_id'],
                'usd_purchase_id' => $allocation['usd_purchase_id'],
                'costing_method' => $method,
                'quantity_used' => $allocation['quantity'],
                'purchase_rate' => $allocation['rate'],
                'cost_amount' => $allocation['cost'],
                'consumed_at' => now(),
            ]);
        }
    }

    private function invoiceNumber(Sale $sale): string
    {
        $prefix = mb_strtoupper(mb_trim((string) $this->settings->get('invoice_prefix', 'INV'))) ?: 'INV';

        return $prefix.'-'.now()->format('Ym').'-'.mb_str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT);
    }
}
