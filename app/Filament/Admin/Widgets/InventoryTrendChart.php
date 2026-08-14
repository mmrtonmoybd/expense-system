<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\InventoryTransaction;
use App\Models\Sale;
use App\Models\UsdPurchase;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class InventoryTrendChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'USD purchases, sales and inventory';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewInternalCosts') ?? false;
    }

    protected function getData(): array
    {
        $dates = collect(range(13, 0))->map(fn (int $offset): Carbon => today()->subDays($offset));
        $from = $dates->first()->toDateString();
        $to = $dates->last()->toDateString();
        $purchases = UsdPurchase::query()->where('status', 'posted')->whereBetween('purchase_date', [$from, $to])->selectRaw('purchase_date, SUM(usd_quantity) total')->groupBy('purchase_date')->pluck('total', 'purchase_date');
        $sales = Sale::query()->where('status', 'posted')->whereBetween('sale_date', [$from, $to])->selectRaw('sale_date, SUM(total_usd) total')->groupBy('sale_date')->pluck('total', 'sale_date');
        $ledger = InventoryTransaction::query()->whereDate('occurred_at', '>=', $from)->whereDate('occurred_at', '<=', $to)->orderBy('occurred_at')->get()->groupBy(fn (InventoryTransaction $transaction): string => $transaction->occurred_at->toDateString())->map->last();

        return [
            'datasets' => [
                ['label' => 'USD purchased', 'data' => $dates->map(fn (Carbon $date) => $purchases[$date->toDateString()] ?? 0), 'borderColor' => '#435cea'],
                ['label' => 'USD sold', 'data' => $dates->map(fn (Carbon $date) => $sales[$date->toDateString()] ?? 0), 'borderColor' => '#d33572'],
                ['label' => 'Closing USD', 'data' => $dates->map(fn (Carbon $date) => $ledger->get($date->toDateString())?->resulting_usd_balance), 'borderColor' => '#16a34a'],
                ['label' => 'Weighted cost', 'data' => $dates->map(fn (Carbon $date) => $ledger->get($date->toDateString())?->resulting_weighted_average_cost), 'borderColor' => '#f59e0b'],
            ],
            'labels' => $dates->map->format((string) config('app.date_format')),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
