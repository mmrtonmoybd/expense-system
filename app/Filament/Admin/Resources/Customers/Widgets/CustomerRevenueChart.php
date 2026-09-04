<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Widgets;

use App\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\DB;

final class CustomerRevenueChart extends ChartWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected ?string $heading = 'Top customers in view';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    protected function getTablePage(): string
    {
        return ListCustomers::class;
    }

    protected function getData(): array
    {
        $rows = DB::table('sales')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereIn('sales.customer_id', $this->summaryQuery()->select('customers.id'))
            ->where('sales.status', 'posted')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->selectRaw('customers.name, COALESCE(SUM(sales.sell_amount), 0) revenue, COALESCE(SUM(sales.profit_amount), 0) profit')
            ->get();

        $datasets = [['label' => 'Revenue', 'data' => $rows->pluck('revenue'), 'backgroundColor' => '#435cea']];

        if ($this->canViewProfit()) {
            $datasets[] = ['label' => 'Gross profit', 'data' => $rows->pluck('profit'), 'backgroundColor' => '#16a34a'];
        }

        return ['datasets' => $datasets, 'labels' => $rows->pluck('name')];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
