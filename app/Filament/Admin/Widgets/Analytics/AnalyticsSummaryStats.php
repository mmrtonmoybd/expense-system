<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Analytics;

use App\Services\FinancialReportService;
use App\Support\AnalyticsFilters;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class AnalyticsSummaryStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewProfit') ?? false;
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $filters = AnalyticsFilters::fromPage($this->pageFilters);
        $report = app(FinancialReportService::class)->profitAndLoss($filters->from, $filters->to);

        $sales = (array) ($filters->applyToSales(
            DB::table('sales')
                ->where('sales.status', 'posted')
                ->whereBetween('sales.sale_date', [$filters->from, $filters->to]),
        )->selectRaw('COUNT(*) sales, COALESCE(SUM(total_usd), 0) usd, COUNT(DISTINCT customer_id) customers')->first() ?? []);

        $purchases = (array) (DB::table('usd_purchases')
            ->where('status', 'posted')
            ->whereBetween('purchase_date', [$filters->from, $filters->to])
            ->selectRaw('COALESCE(SUM(usd_quantity), 0) usd, COALESCE(SUM(bdt_total), 0) bdt')
            ->first() ?? []);

        $usdSold = (string) ($sales['usd'] ?? '0');
        $sellRate = Money::isPositiveUsd($usdSold) ? Money::rateFor($report['revenue'], $usdSold) : '0';
        $buyRate = Money::isPositiveUsd((string) ($purchases['usd'] ?? '0')) ? Money::rateFor((string) $purchases['bdt'], (string) $purchases['usd']) : '0';
        $margin = Money::compareBdt($report['revenue'], '0') === 1
            ? Money::bdt(bcmul(bcdiv($report['net_profit'], $report['revenue'], 16), '100', 16))
            : '0.00';

        return [
            Stat::make('Revenue', '৳ '.number_format((float) $report['revenue'], 2))
                ->description($filters->label())
                ->icon(Heroicon::PresentationChartLine)
                ->color('info')
                ->extraAttributes(['class' => 'finance-dashboard-stat-card']),
            Stat::make('Gross profit', '৳ '.number_format((float) $report['gross_profit'], 2))
                ->description('Cost of USD sold ৳ '.number_format((float) $report['cost_of_usd_sold'], 2))
                ->icon(Heroicon::ArrowTrendingUp)
                ->color('success')
                ->extraAttributes(['class' => 'finance-dashboard-stat-card']),
            Stat::make('Overheads', '৳ '.number_format((float) bcadd($report['office_expenses'], $report['salary_expense'], 2), 2))
                ->description('Office ৳ '.number_format((float) $report['office_expenses'], 2).' · Salary ৳ '.number_format((float) $report['salary_expense'], 2))
                ->icon(Heroicon::ReceiptPercent)
                ->color('danger')
                ->extraAttributes(['class' => 'finance-dashboard-stat-card']),
            Stat::make('Net profit', '৳ '.number_format((float) $report['net_profit'], 2))
                ->description("Net margin {$margin}%")
                ->icon(Heroicon::Calculator)
                ->color((float) $report['net_profit'] >= 0 ? 'primary' : 'danger')
                ->extraAttributes(['class' => 'finance-dashboard-stat-card']),
            Stat::make('USD sold', number_format((float) $usdSold, 2))
                ->description(number_format((float) ($sales['sales'] ?? 0)).' sales to '.number_format((float) ($sales['customers'] ?? 0)).' customers')
                ->icon(Heroicon::ArrowUpTray)
                ->color('gray')
                ->extraAttributes(['class' => 'finance-dashboard-stat-card']),
            Stat::make('USD bought', number_format((float) ($purchases['usd'] ?? 0), 2))
                ->description('৳ '.number_format((float) ($purchases['bdt'] ?? 0), 2).' spent')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->extraAttributes(['class' => 'finance-dashboard-stat-card']),
            Stat::make('Average sell rate', '৳ '.number_format((float) $sellRate, 4))
                ->description('Per USD, across the range')
                ->icon(Heroicon::Tag)
                ->color('warning')
                ->extraAttributes(['class' => 'finance-dashboard-stat-card']),
            Stat::make('Average buy rate', '৳ '.number_format((float) $buyRate, 4))
                ->description('Spread ৳ '.number_format((float) bcsub($sellRate, $buyRate, 4), 4))
                ->icon(Heroicon::Scale)
                ->color('warning')
                ->extraAttributes(['class' => 'finance-dashboard-stat-card']),
        ];
    }
}
