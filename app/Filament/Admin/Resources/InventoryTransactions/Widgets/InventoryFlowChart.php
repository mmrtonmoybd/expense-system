<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions\Widgets;

use App\Filament\Admin\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;

final class InventoryFlowChart extends ChartWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected ?string $heading = 'Inventory movement in view';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewInternalCosts') ?? false;
    }

    protected function getTablePage(): string
    {
        return ListInventoryTransactions::class;
    }

    protected function getData(): array
    {
        $rows = $this->summaryQuery()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(200)
            ->get(['occurred_at', 'usd_in', 'usd_out', 'resulting_usd_balance', 'resulting_weighted_average_cost']);

        return [
            'datasets' => [
                ['label' => 'USD in', 'data' => $rows->pluck('usd_in'), 'borderColor' => '#16a34a'],
                ['label' => 'USD out', 'data' => $rows->pluck('usd_out'), 'borderColor' => '#d33572'],
                ['label' => 'Closing USD', 'data' => $rows->pluck('resulting_usd_balance'), 'borderColor' => '#435cea'],
                ['label' => 'Average cost', 'data' => $rows->pluck('resulting_weighted_average_cost'), 'borderColor' => '#f59e0b'],
            ],
            'labels' => $rows->map(fn (object $row): string => Carbon::parse((string) $row->occurred_at)->format((string) config('app.datetime_format'))),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
