<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Platforms\Widgets;

use App\Models\Platform;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class PlatformPerformanceStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $platform = $this->record;

        if (! $platform instanceof Platform) {
            return [];
        }

        $totals = (array) (DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sale_lines.platform_id', $platform->getKey())
            ->where('sales.status', 'posted')
            ->selectRaw('COUNT(DISTINCT sales.id) sales, COUNT(DISTINCT sales.customer_id) customers, COALESCE(SUM(sale_lines.usd_quantity), 0) usd, COALESCE(SUM(sale_lines.revenue), 0) revenue, COALESCE(SUM(sale_lines.gross_profit), 0) profit')
            ->first() ?? []);

        $assignments = DB::table('customer_platforms')->where('platform_id', $platform->getKey())->count();
        $usd = (string) ($totals['usd'] ?? '0');
        $averageRate = Money::isPositiveUsd($usd) ? Money::rateFor((string) $totals['revenue'], $usd) : '0';

        return [
            Stat::make('Assigned customers', number_format((float) $assignments))
                ->description(number_format((float) ($totals['customers'] ?? 0)).' have actually bought')
                ->icon(Heroicon::UserGroup)
                ->color('gray'),
            Stat::make('USD sold', number_format((float) $usd, 2))
                ->description(number_format((float) ($totals['sales'] ?? 0)).' posted sales')
                ->icon(Heroicon::Banknotes)
                ->color('info'),
            Stat::make('Revenue', '৳ '.number_format((float) ($totals['revenue'] ?? 0), 2))
                ->description('Average ৳ '.number_format((float) $averageRate, 4).' / USD')
                ->icon(Heroicon::PresentationChartLine)
                ->color('success'),
            ...((auth()->user()?->can('ViewProfit') ?? false) ? [
                Stat::make('Gross profit', '৳ '.number_format((float) ($totals['profit'] ?? 0), 2))
                    ->icon(Heroicon::ArrowTrendingUp)
                    ->color('primary'),
            ] : []),
        ];
    }
}
