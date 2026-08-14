<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CustomerPlatform;
use App\Models\Sale;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RecalculatePendingSaleAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(Sale $sale, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $user): Sale {
            $lockedSale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($sale->getKey());

            if ($lockedSale->status !== 'pending') {
                throw new LogicException('Only pending sales can be recalculated.');
            }

            $lockedSale->load('lines');

            if ($lockedSale->lines->isEmpty()) {
                throw new LogicException('A pending sale must contain at least one platform line.');
            }

            $totalUsd = '0';
            $totalRevenue = '0';
            $platformIds = [];

            foreach ($lockedSale->lines as $line) {
                if (isset($platformIds[$line->platform_id])) {
                    throw new LogicException('A platform may only appear once in a sale.');
                }

                $platformIds[$line->platform_id] = true;

                if (bccomp((string) $line->usd_quantity, '0', 8) !== 1
                    || bccomp((string) $line->selling_rate, '0', 8) !== 1) {
                    throw new LogicException('USD quantity and selling rate must be greater than zero.');
                }

                $defaultRate = CustomerPlatform::query()
                    ->where('customer_id', $lockedSale->customer_id)
                    ->where('platform_id', $line->platform_id)
                    ->value('current_selling_rate');

                if ($defaultRate === null) {
                    throw new LogicException('Every platform line must be assigned to the selected customer.');
                }

                $isOverridden = bccomp((string) $defaultRate, (string) $line->selling_rate, 8) !== 0;

                if ($isOverridden && ! $user->can('OverrideRate:Sale')) {
                    throw new LogicException('You are not authorized to override customer selling rates.');
                }

                $line->update([
                    'is_rate_overridden' => $isOverridden,
                    'rate_overridden_by' => $isOverridden ? $user->id : null,
                ]);

                if ($isOverridden) {
                    $this->auditLogger->record(
                        $user,
                        'sale_rate_overridden',
                        $line,
                        oldValues: ['selling_rate' => (string) $defaultRate],
                        newValues: ['selling_rate' => (string) $line->selling_rate],
                        reason: $line->rate_override_reason,
                    );
                }

                $totalUsd = bcadd($totalUsd, (string) $line->usd_quantity, 8);
                $totalRevenue = bcadd(
                    $totalRevenue,
                    bcmul((string) $line->usd_quantity, (string) $line->selling_rate, 2),
                    2,
                );
            }

            $oldValues = [
                'total_usd' => (string) $lockedSale->total_usd,
                'total_bdt_revenue' => (string) $lockedSale->total_bdt_revenue,
            ];

            $lockedSale->update([
                'total_usd' => $totalUsd,
                'total_bdt_revenue' => $totalRevenue,
                'total_inventory_cost' => '0',
                'total_gross_profit' => '0',
            ]);

            $this->auditLogger->record(
                $user,
                'pending_sale_updated',
                $lockedSale,
                oldValues: $oldValues,
                newValues: [
                    'total_usd' => $totalUsd,
                    'total_bdt_revenue' => $totalRevenue,
                ],
            );

            return $lockedSale->refresh()->load('lines');
        }, attempts: 5);
    }
}
