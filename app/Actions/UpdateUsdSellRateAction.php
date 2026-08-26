<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\UsdSellRate;
use App\Models\User;
use App\Services\UsdSellRateService;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\SettingsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

/** Sets the current operational USD selling rate and keeps a full rate history. */
final readonly class UpdateUsdSellRateAction
{
    public function __construct(
        private SettingsRepository $settings,
        private UsdSellRateService $sellRates,
        private AuditLogger $auditLogger,
    ) {}

    public function execute(string $rate, User $user, ?string $note = null, ?Carbon $effectiveFrom = null): UsdSellRate
    {
        Gate::forUser($user)->authorize('Manage:SellRate');

        if (! Money::isPositiveUsd($rate)) {
            throw new LogicException('The USD selling rate must be greater than zero.');
        }

        return DB::transaction(function () use ($rate, $user, $note, $effectiveFrom): UsdSellRate {
            $previous = $this->sellRates->configuredRate();
            $rate = Money::rate($rate);

            $history = UsdSellRate::query()->create([
                'rate' => $rate,
                'previous_rate' => $previous,
                'effective_from' => $effectiveFrom ?? now(),
                'changed_by' => $user->id,
                'note' => $note,
            ]);

            $this->settings->putMany([UsdSellRateService::SettingKey => $rate]);

            $this->auditLogger->record(
                $user,
                'usd_sell_rate_updated',
                $history,
                oldValues: ['rate' => $previous],
                newValues: ['rate' => $rate],
                reason: $note,
            );

            return $history;
        }, attempts: 5);
    }
}
