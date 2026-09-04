<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions\Widgets;

use App\Filament\Admin\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;

final class InventoryFlowStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListInventoryTransactions::class;
    }

    protected function getStats(): array
    {
        $totals = $this->summarise([
            'movements' => 'COUNT(*)',
            'usd_in' => 'COALESCE(SUM(usd_in), 0)',
            'usd_out' => 'COALESCE(SUM(usd_out), 0)',
            'bdt_in' => 'COALESCE(SUM(bdt_value_in), 0)',
            'bdt_out' => 'COALESCE(SUM(bdt_value_out), 0)',
        ]);

        $closing = $this->summaryQuery()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first(['resulting_usd_balance', 'resulting_inventory_value_bdt', 'resulting_weighted_average_cost']);

        $netUsd = Money::subUsd($totals['usd_in'], $totals['usd_out']);

        return [
            $this->summaryStat('Movements in view', $this->quantity($totals['movements'], 0), Heroicon::ArrowsRightLeft, 'gray'),
            $this->summaryStat('USD in', $this->quantity($totals['usd_in']), Heroicon::ArrowDownTray, 'success'),
            $this->summaryStat('USD out', $this->quantity($totals['usd_out']), Heroicon::ArrowUpTray, 'danger', 'Net '.$this->quantity($netUsd)),
            ...($this->canViewCosts() ? [
                $this->summaryStat(
                    'Closing position',
                    $this->quantity($closing->resulting_usd_balance ?? 0).' USD',
                    Heroicon::Wallet,
                    'primary',
                    'Book value '.$this->money($closing->resulting_inventory_value_bdt ?? 0).' · '.$this->money($closing->resulting_weighted_average_cost ?? 0, 4).' / USD',
                ),
            ] : []),
        ];
    }
}
