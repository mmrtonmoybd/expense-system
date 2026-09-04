<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs\Widgets;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Str;

final class AuditActionChart extends ChartWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected ?string $heading = 'Actions in view';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected bool $isCollapsible = true;

    protected function getTablePage(): string
    {
        return ListAuditLogs::class;
    }

    protected function getData(): array
    {
        $rows = $this->summaryQuery()
            ->selectRaw('audit_logs.action, COUNT(*) total')
            ->groupBy('audit_logs.action')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Events',
                'data' => $rows->pluck('total'),
                'backgroundColor' => '#435cea',
            ]],
            'labels' => $rows->map(fn (object $row): string => Str::headline((string) $row->action)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
