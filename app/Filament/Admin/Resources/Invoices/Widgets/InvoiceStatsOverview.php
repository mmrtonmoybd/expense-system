<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Widgets;

use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

final class InvoiceStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListInvoices::class;
    }

    protected function getStats(): array
    {
        $counts = $this->summarise([
            'invoices' => 'COUNT(*)',
            'issued' => "SUM(CASE WHEN invoices.status = 'issued' THEN 1 ELSE 0 END)",
            'cancelled' => "SUM(CASE WHEN invoices.status = 'cancelled' THEN 1 ELSE 0 END)",
        ]);

        $value = (array) (DB::table('sales')
            ->whereIn('id', $this->summaryQuery()->select('invoices.sale_id'))
            ->selectRaw('COALESCE(SUM(total_usd), 0) usd, COALESCE(SUM(sell_amount), 0) revenue')
            ->first() ?? []);

        return [
            $this->summaryStat('Invoices in view', $this->quantity($counts['invoices'], 0), Heroicon::DocumentText, 'gray'),
            $this->summaryStat('Issued', $this->quantity($counts['issued'], 0), Heroicon::CheckCircle, 'success'),
            $this->summaryStat('Cancelled', $this->quantity($counts['cancelled'], 0), Heroicon::XCircle, (int) $counts['cancelled'] > 0 ? 'danger' : 'gray'),
            $this->summaryStat('Invoiced value', $this->money($value['revenue'] ?? 0), Heroicon::Banknotes, 'primary', $this->quantity($value['usd'] ?? 0).' USD'),
        ];
    }
}
