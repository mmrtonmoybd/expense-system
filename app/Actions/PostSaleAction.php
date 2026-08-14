<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CustomerPlatform;
use App\Models\InventoryState;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PostSaleAction
{
    public function __construct(private AuditLogger $auditLogger, private SettingsRepository $settings) {}

    public function execute(Sale $sale, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $user): Sale {
            $sale = Sale::query()->with('lines')->lockForUpdate()->findOrFail($sale->id);

            if (in_array($sale->status, ['posted', 'reversed'], true)) {
                throw new LogicException('Only unposted sales can be posted.');
            }

            $totalUsd = '0';
            foreach ($sale->lines as $line) {
                if (bccomp((string) $line->usd_quantity, '0', 8) !== 1 || bccomp((string) $line->selling_rate, '0', 8) !== 1) {
                    throw new LogicException('Every sale line requires positive USD quantity and selling rate.');
                }
                $assignmentExists = CustomerPlatform::query()
                    ->where('customer_id', $sale->customer_id)
                    ->where('platform_id', $line->platform_id)
                    ->exists();
                if (! $assignmentExists) {
                    throw new LogicException('Every platform line must be assigned to the selected customer.');
                }
                $totalUsd = bcadd($totalUsd, (string) $line->usd_quantity, 8);
            }

            $state = InventoryState::query()->lockForUpdate()->firstOrCreate([], [
                'usd_balance' => '0', 'inventory_value_bdt' => '0', 'weighted_average_cost' => '0',
            ]);
            if (bccomp($totalUsd, (string) $state->usd_balance, 8) === 1) {
                throw new LogicException("You only have {$state->usd_balance} USD available. This sale requires {$totalUsd}.");
            }

            $costRate = (string) $state->weighted_average_cost;
            $revenue = '0';
            $cost = '0';
            foreach ($sale->lines as $line) {
                $lineRevenue = bcmul((string) $line->usd_quantity, (string) $line->selling_rate, 2);
                $lineCost = bcmul((string) $line->usd_quantity, $costRate, 2);
                $line->update(['weighted_cost_rate' => $costRate, 'revenue' => $lineRevenue, 'inventory_cost' => $lineCost, 'gross_profit' => bcsub($lineRevenue, $lineCost, 2)]);
                $revenue = bcadd($revenue, $lineRevenue, 2);
                $cost = bcadd($cost, $lineCost, 2);
            }

            $newBalance = bcsub((string) $state->usd_balance, $totalUsd, 8);
            $newValue = bcsub((string) $state->inventory_value_bdt, $cost, 2);
            $newRate = bccomp($newBalance, '0', 8) === 1 ? bcdiv($newValue, $newBalance, 8) : '0';
            $invoiceNumber = $sale->invoice_number ?: $this->invoiceNumber($sale);
            $sale->update(['invoice_number' => $invoiceNumber, 'total_usd' => $totalUsd, 'total_bdt_revenue' => $revenue, 'total_inventory_cost' => $cost, 'total_gross_profit' => bcsub($revenue, $cost, 2), 'status' => 'posted', 'approval_status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'posted_at' => now()]);
            $state->update(['usd_balance' => $newBalance, 'inventory_value_bdt' => $newValue, 'weighted_average_cost' => $newRate]);
            InventoryTransaction::query()->create(['transaction_type' => 'sale', 'source_type' => Sale::class, 'source_id' => $sale->id, 'usd_out' => $totalUsd, 'cost_rate' => $costRate, 'bdt_value_out' => $cost, 'resulting_usd_balance' => $newBalance, 'resulting_inventory_value_bdt' => $newValue, 'resulting_weighted_average_cost' => $newRate, 'created_by' => $user->id, 'occurred_at' => now(), 'note' => $sale->note]);
            Invoice::query()->firstOrCreate(['sale_id' => $sale->id], ['invoice_number' => $invoiceNumber, 'invoice_date' => $sale->sale_date, 'status' => 'issued']);
            $this->auditLogger->record($user, 'sale_posted', $sale, newValues: ['total_usd' => $totalUsd, 'revenue' => $revenue, 'inventory_cost' => $cost]);

            return $sale->refresh()->load('lines', 'invoice');
        }, attempts: 5);
    }

    private function invoiceNumber(Sale $sale): string
    {
        $prefix = mb_strtoupper(mb_trim((string) $this->settings->get('invoice_prefix', 'INV'))) ?: 'INV';

        return $prefix.'-'.now()->format('Ym').'-'.mb_str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT);
    }
}
