<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CustomerPlatform;
use App\Models\Sale;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\SettingsRepository;
use LogicException;

final readonly class SubmitSaleAction
{
    public function __construct(private SettingsRepository $settings, private PostSaleAction $postSale, private AuditLogger $auditLogger) {}

    public function execute(Sale $sale, User $user): Sale
    {
        if ($sale->status !== 'draft') {
            throw new LogicException('Only draft sales can be submitted.');
        }
        $sale->loadMissing('lines');
        foreach ($sale->lines as $line) {
            $defaultRate = CustomerPlatform::query()
                ->where('customer_id', $sale->customer_id)
                ->where('platform_id', $line->platform_id)
                ->value('current_selling_rate');
            if ($defaultRate === null) {
                throw new LogicException('Every platform line must be assigned to the selected customer.');
            }
            $overridden = bccomp((string) $defaultRate, (string) $line->selling_rate, 8) !== 0;
            if ($overridden && ! $user->can('OverrideRate:Sale')) {
                throw new LogicException('You are not authorized to override customer selling rates.');
            }
            if ($overridden) {
                $line->update(['is_rate_overridden' => true, 'rate_overridden_by' => $user->id]);
                $this->auditLogger->record($user, 'sale_rate_overridden', $line, oldValues: ['selling_rate' => (string) $defaultRate], newValues: ['selling_rate' => (string) $line->selling_rate], reason: $line->rate_override_reason);
            }
        }
        if (! $this->settings->boolean('sale_approval_required') || $user->can('Approve:Sale')) {
            return $this->postSale->execute($sale, $user);
        }

        $totalUsd = '0';
        $totalRevenue = '0';

        foreach ($sale->lines as $line) {
            $totalUsd = Money::addUsd($totalUsd, (string) $line->usd_quantity);
            $totalRevenue = Money::addBdt(
                $totalRevenue,
                Money::amount((string) $line->usd_quantity, (string) $line->selling_rate),
            );
        }

        $sale->update([
            'total_usd' => $totalUsd,
            'total_bdt_revenue' => $totalRevenue,
            'total_inventory_cost' => '0',
            'total_gross_profit' => '0',
            'status' => 'pending',
            'approval_status' => 'pending',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $this->auditLogger->record($user, 'sale_submitted', $sale);

        return $sale->refresh();
    }
}
