<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Widgets;

use App\Filament\Admin\Resources\Sales\Pages\ListSales;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;

final class SaleStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListSales::class;
    }

    protected function getStats(): array
    {
        $totals = $this->summarise([
            'sales' => 'COUNT(*)',
            'usd' => 'COALESCE(SUM(total_usd), 0)',
            'revenue' => 'COALESCE(SUM(sell_amount), 0)',
            'cost' => 'COALESCE(SUM(cost_amount), 0)',
            'profit' => 'COALESCE(SUM(profit_amount), 0)',
            'posted' => "SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END)",
            'pending' => "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END)",
        ]);

        $averageRate = Money::isPositiveUsd($totals['usd'])
            ? Money::rateFor($totals['revenue'], $totals['usd'])
            : '0';
        $margin = Money::compareBdt($totals['revenue'], '0') === 1
            ? Money::bdt(bcmul(bcdiv($totals['profit'], $totals['revenue'], 16), '100', 16))
            : '0.00';

        return [
            $this->summaryStat('Sales in view', $this->quantity($totals['sales'], 0), Heroicon::ClipboardDocumentList, 'gray', "{$totals['posted']} posted · {$totals['pending']} pending"),
            $this->summaryStat('USD sold', $this->quantity($totals['usd']), Heroicon::Banknotes, 'info'),
            $this->summaryStat('Revenue', $this->money($totals['revenue']), Heroicon::PresentationChartLine, 'success', 'Average '.$this->money($averageRate, 4).' / USD'),
            ...($this->canViewCosts() ? [
                $this->summaryStat('Inventory cost', $this->money($totals['cost']), Heroicon::Scale, 'warning'),
            ] : []),
            ...($this->canViewProfit() ? [
                $this->summaryStat('Gross profit', $this->money($totals['profit']), Heroicon::ArrowTrendingUp, 'primary', "Margin {$margin}%"),
            ] : []),
        ];
    }
}
