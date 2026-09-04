<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Widgets;

use App\Models\Customer;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CustomerSalesTrendChart extends ChartWidget
{
    public ?Model $record = null;

    protected ?string $heading = 'Monthly trading with this customer';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    protected function getData(): array
    {
        $customer = $this->record;

        if (! $customer instanceof Customer) {
            return ['datasets' => [], 'labels' => []];
        }

        $months = collect(range(11, 0))->map(fn (int $offset): Carbon => now()->subMonths($offset)->startOfMonth());

        $rows = DB::table('sales')
            ->where('customer_id', $customer->getKey())
            ->where('status', 'posted')
            ->where('sale_date', '>=', $months->first()->toDateString())
            ->groupBy('sale_date')
            ->selectRaw('sale_date, COALESCE(SUM(total_usd), 0) usd, COALESCE(SUM(sell_amount), 0) revenue')
            ->get()
            ->groupBy(fn (object $row): string => Carbon::parse((string) $row->sale_date)->format('Y-m'))
            ->map(fn ($group): array => [
                'usd' => $group->sum(fn (object $row): float => (float) $row->usd),
                'revenue' => $group->sum(fn (object $row): float => (float) $row->revenue),
            ]);

        return [
            'datasets' => [
                ['label' => 'USD', 'data' => $months->map(fn (Carbon $month) => $rows[$month->format('Y-m')]['usd'] ?? 0), 'borderColor' => '#0891b2'],
                ['label' => 'Revenue', 'data' => $months->map(fn (Carbon $month) => $rows[$month->format('Y-m')]['revenue'] ?? 0), 'borderColor' => '#435cea', 'backgroundColor' => '#435cea33'],
            ],
            'labels' => $months->map->format('M Y'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
