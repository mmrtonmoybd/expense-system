<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

final class SaleBreakdownChart extends ChartWidget
{
    public ?Model $record = null;

    protected ?string $heading = 'Revenue, cost and profit by platform line';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewInternalCosts') ?? false;
    }

    protected function getData(): array
    {
        $sale = $this->record;

        if (! $sale instanceof Sale) {
            return ['datasets' => [], 'labels' => []];
        }

        $lines = $sale->lines()->with('platform')->get();

        $datasets = [
            ['label' => 'Revenue', 'data' => $lines->pluck('revenue'), 'backgroundColor' => '#435cea'],
            ['label' => 'Inventory cost', 'data' => $lines->pluck('inventory_cost'), 'backgroundColor' => '#f59e0b'],
        ];

        if (auth()->user()?->can('ViewProfit') ?? false) {
            $datasets[] = ['label' => 'Gross profit', 'data' => $lines->pluck('gross_profit'), 'backgroundColor' => '#16a34a'];
        }

        return ['datasets' => $datasets, 'labels' => $lines->map(fn ($line): string => $line->platform?->name ?? 'Platform')];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
