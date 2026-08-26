<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sale;
use App\Models\UsdSellRate;
use App\Support\Money;
use App\Support\SettingsRepository;
use Illuminate\Support\Carbon;

/**
 * The operational USD selling rate offered to customers right now.
 *
 * This is a rate the business maintains explicitly. It is never derived from inventory cost,
 * FIFO/LIFO cost, or a purchase average. Until an operator sets one, the most recent posted
 * sale rate is used as a fallback so the dashboard is not empty.
 */
final readonly class UsdSellRateService
{
    public const string SettingKey = 'current_usd_sell_rate';

    public function __construct(private SettingsRepository $settings) {}

    /** The configured operational rate, or null when nobody has set one yet. */
    public function configuredRate(): ?string
    {
        $rate = $this->settings->get(self::SettingKey);

        return filled($rate) && Money::isPositiveUsd((string) $rate) ? Money::rate((string) $rate) : null;
    }

    /** The rate shown to operators: the configured rate, else the latest posted sale rate. */
    public function currentRate(): ?string
    {
        return $this->configuredRate() ?? $this->latestPostedSaleRate();
    }

    public function isFallback(): bool
    {
        return $this->configuredRate() === null;
    }

    /** When the shown rate last changed. */
    public function updatedAt(): ?Carbon
    {
        if ($this->configuredRate() !== null) {
            return UsdSellRate::query()->latest('effective_from')->value('effective_from');
        }

        return Sale::query()->where('status', 'posted')->latest('posted_at')->value('posted_at');
    }

    /** Rate of the most recent posted sale, weighted across its platform lines. */
    private function latestPostedSaleRate(): ?string
    {
        $sale = Sale::query()
            ->where('status', 'posted')
            ->where('total_usd', '>', 0)
            ->latest('posted_at')
            ->latest('id')
            ->first(['sell_rate', 'total_bdt_revenue', 'total_usd']);

        if ($sale === null) {
            return null;
        }

        return Money::isPositiveUsd($sale->sell_rate ?? '0')
            ? Money::rate($sale->sell_rate)
            : Money::rateFor((string) $sale->total_bdt_revenue, (string) $sale->total_usd);
    }
}
