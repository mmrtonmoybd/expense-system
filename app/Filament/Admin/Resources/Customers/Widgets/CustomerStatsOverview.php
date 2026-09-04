<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Widgets;

use App\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

final class CustomerStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListCustomers::class;
    }

    protected function getStats(): array
    {
        $counts = $this->summarise([
            'customers' => 'COUNT(*)',
            'active' => 'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)',
        ]);

        $sales = (array) (DB::table('sales')
            ->whereIn('customer_id', $this->summaryQuery()->select('customers.id'))
            ->where('status', 'posted')
            ->selectRaw('COUNT(*) sales, COALESCE(SUM(total_usd), 0) usd, COALESCE(SUM(sell_amount), 0) revenue, COALESCE(SUM(profit_amount), 0) profit, COUNT(DISTINCT customer_id) buyers')
            ->first() ?? []);

        return [
            $this->summaryStat('Customers in view', $this->quantity($counts['customers'], 0), Heroicon::UserGroup, 'gray', "{$counts['active']} active"),
            $this->summaryStat('Buying customers', $this->quantity($sales['buyers'] ?? 0, 0), Heroicon::ShoppingBag, 'info', $this->quantity($sales['sales'] ?? 0, 0).' posted sales'),
            $this->summaryStat('USD sold to them', $this->quantity($sales['usd'] ?? 0), Heroicon::Banknotes, 'success'),
            ...($this->canViewProfit()
                ? [$this->summaryStat('Revenue', $this->money($sales['revenue'] ?? 0), Heroicon::PresentationChartLine, 'primary', 'Gross profit '.$this->money($sales['profit'] ?? 0))]
                : [$this->summaryStat('Revenue', $this->money($sales['revenue'] ?? 0), Heroicon::PresentationChartLine, 'primary')]),
        ];
    }
}
