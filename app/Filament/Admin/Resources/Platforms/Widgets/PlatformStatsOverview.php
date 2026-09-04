<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Platforms\Widgets;

use App\Filament\Admin\Resources\Platforms\Pages\ListPlatforms;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

final class PlatformStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListPlatforms::class;
    }

    protected function getStats(): array
    {
        $counts = $this->summarise([
            'platforms' => 'COUNT(*)',
            'active' => 'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)',
        ]);

        $ids = $this->summaryQuery()->select('platforms.id');
        $assignments = DB::table('customer_platforms')->whereIn('platform_id', $ids)->count();
        $sales = (array) (DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereIn('sale_lines.platform_id', $ids)
            ->where('sales.status', 'posted')
            ->selectRaw('COALESCE(SUM(sale_lines.usd_quantity), 0) usd, COALESCE(SUM(sale_lines.revenue), 0) revenue')
            ->first() ?? []);

        return [
            $this->summaryStat('Platforms in view', $this->quantity($counts['platforms'], 0), Heroicon::RectangleStack, 'gray', "{$counts['active']} active"),
            $this->summaryStat('Customer assignments', $this->quantity($assignments, 0), Heroicon::UserGroup, 'info'),
            $this->summaryStat('USD sold through them', $this->quantity($sales['usd'] ?? 0), Heroicon::Banknotes, 'success'),
            $this->summaryStat('Revenue', $this->money($sales['revenue'] ?? 0), Heroicon::PresentationChartLine, 'primary'),
        ];
    }
}
