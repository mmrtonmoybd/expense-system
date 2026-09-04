<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Analytics;

use App\Support\AnalyticsFilters;
use App\Support\Money;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the business actually earns per dollar: the rate it buys at, the rate it sells at, and
 * the gap between them over time. The gap is the margin before overheads.
 */
final class RateSpreadChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Buy rate, sell rate and spread';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected ?string $maxHeight = '300px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewInternalCosts') ?? false;
    }

    protected function getData(): array
    {
        $filters = AnalyticsFilters::fromPage($this->pageFilters);

        $purchases = $this->bucket($filters, DB::table('usd_purchases')
            ->where('status', 'posted')
            ->whereBetween('purchase_date', [$filters->from, $filters->to])
            ->groupBy('purchase_date')
            ->selectRaw('purchase_date as period_date, COALESCE(SUM(usd_quantity), 0) usd, COALESCE(SUM(bdt_total), 0) bdt')
            ->get());

        $sales = $this->bucket($filters, $filters->applyToSales(
            DB::table('sales')
                ->where('sales.status', 'posted')
                ->whereBetween('sales.sale_date', [$filters->from, $filters->to]),
        )
            ->groupBy('sales.sale_date')
            ->selectRaw('sales.sale_date as period_date, COALESCE(SUM(sales.total_usd), 0) usd, COALESCE(SUM(sales.sell_amount), 0) bdt')
            ->get());

        $labels = [];
        $buyRates = [];
        $sellRates = [];
        $spreads = [];

        foreach ($filters->periods() as $start) {
            $key = $start->toDateString();
            $buy = $this->rate($purchases[$key] ?? null);
            $sell = $this->rate($sales[$key] ?? null);

            $labels[] = $filters->periodLabel($start);
            $buyRates[] = $buy;
            $sellRates[] = $sell;
            $spreads[] = ($buy === null || $sell === null) ? null : Money::rate(bcsub($sell, $buy, Money::RateScale));
        }

        return [
            'datasets' => [
                ['label' => 'Average buy rate', 'data' => $buyRates, 'borderColor' => '#f59e0b', 'spanGaps' => true],
                ['label' => 'Average sell rate', 'data' => $sellRates, 'borderColor' => '#435cea', 'spanGaps' => true],
                ['label' => 'Spread per USD', 'data' => $spreads, 'borderColor' => '#16a34a', 'backgroundColor' => '#16a34a33', 'fill' => true, 'spanGaps' => true],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * Roll daily totals up into the selected period.
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, array{usd:string, bdt:string}>
     */
    private function bucket(AnalyticsFilters $filters, Collection $rows): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $key = $filters->periodStart(Carbon::parse((string) $row->period_date))->toDateString();
            $buckets[$key] ??= ['usd' => '0', 'bdt' => '0'];
            $buckets[$key]['usd'] = Money::addUsd($buckets[$key]['usd'], (string) $row->usd);
            $buckets[$key]['bdt'] = Money::addBdt($buckets[$key]['bdt'], (string) $row->bdt);
        }

        return $buckets;
    }

    /** @param array{usd:string, bdt:string}|null $bucket */
    private function rate(?array $bucket): ?string
    {
        if ($bucket === null || ! Money::isPositiveUsd($bucket['usd'])) {
            return null;
        }

        return Money::rateFor($bucket['bdt'], $bucket['usd']);
    }
}
