<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Widgets;

use App\Filament\Admin\Resources\UsdPurchases\Pages\ListUsdPurchases;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;

final class UsdPurchaseStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListUsdPurchases::class;
    }

    protected function getStats(): array
    {
        $totals = $this->summarise([
            'purchases' => 'COUNT(*)',
            'usd' => 'COALESCE(SUM(usd_quantity), 0)',
            'bdt' => 'COALESCE(SUM(bdt_total), 0)',
            'posted' => "SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END)",
            'draft' => "SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END)",
            'best' => 'COALESCE(MIN(purchase_rate), 0)',
            'worst' => 'COALESCE(MAX(purchase_rate), 0)',
        ]);

        $averageRate = Money::isPositiveUsd($totals['usd']) ? Money::rateFor($totals['bdt'], $totals['usd']) : '0';

        return [
            $this->summaryStat('Purchases in view', $this->quantity($totals['purchases'], 0), Heroicon::ClipboardDocumentList, 'gray', "{$totals['posted']} posted · {$totals['draft']} draft"),
            $this->summaryStat('USD bought', $this->quantity($totals['usd']), Heroicon::ArrowDownTray, 'info'),
            $this->summaryStat('BDT spent', $this->money($totals['bdt']), Heroicon::Wallet, 'warning'),
            $this->summaryStat('Average buy rate', $this->money($averageRate, 4), Heroicon::Scale, 'primary', 'Best '.$this->money($totals['best'], 4).' · Worst '.$this->money($totals['worst'], 4)),
        ];
    }
}
