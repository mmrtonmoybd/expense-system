<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs\Widgets;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Carbon;

final class AuditLogStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListAuditLogs::class;
    }

    protected function getStats(): array
    {
        $totals = $this->summarise([
            'events' => 'COUNT(*)',
            'users' => 'COUNT(DISTINCT audit_logs.user_id)',
            'actions' => 'COUNT(DISTINCT audit_logs.action)',
            'models' => 'COUNT(DISTINCT audit_logs.auditable_type)',
            'first_at' => 'MIN(audit_logs.occurred_at)',
            'last_at' => 'MAX(audit_logs.occurred_at)',
        ]);

        $format = (string) config('app.datetime_format');
        $window = filled($totals['first_at'])
            ? Carbon::parse($totals['first_at'])->format($format).' → '.Carbon::parse($totals['last_at'])->format($format)
            : 'No events in view';

        return [
            $this->summaryStat('Events in view', $this->quantity($totals['events'], 0), Heroicon::ClipboardDocumentList, 'gray', $window),
            $this->summaryStat('Distinct users', $this->quantity($totals['users'], 0), Heroicon::UserGroup, 'info'),
            $this->summaryStat('Distinct actions', $this->quantity($totals['actions'], 0), Heroicon::BoltSlash, 'warning'),
            $this->summaryStat('Models touched', $this->quantity($totals['models'], 0), Heroicon::RectangleStack, 'primary'),
        ];
    }
}
