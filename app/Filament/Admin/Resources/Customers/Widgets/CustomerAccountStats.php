<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Widgets;

use App\Models\Customer;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Lifetime trading position for one customer. */
final class CustomerAccountStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $customer = $this->record;

        if (! $customer instanceof Customer) {
            return [];
        }

        $totals = (array) (DB::table('sales')
            ->where('customer_id', $customer->getKey())
            ->where('status', 'posted')
            ->selectRaw('COUNT(*) sales, COALESCE(SUM(total_usd), 0) usd, COALESCE(SUM(sell_amount), 0) revenue, COALESCE(SUM(profit_amount), 0) profit, MAX(sale_date) last_sale')
            ->first() ?? []);

        $usd = (string) ($totals['usd'] ?? '0');
        $averageRate = Money::isPositiveUsd($usd) ? Money::rateFor((string) $totals['revenue'], $usd) : '0';
        $lastSale = filled($totals['last_sale'] ?? null)
            ? Carbon::parse((string) $totals['last_sale'])->format((string) config('app.date_format'))
            : 'Never';

        return [
            Stat::make('Posted sales', number_format((float) ($totals['sales'] ?? 0)))
                ->description("Last sale {$lastSale}")
                ->icon(Heroicon::ClipboardDocumentList)
                ->color('gray'),
            Stat::make('USD bought from us', number_format((float) $usd, 2))
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
