<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\Admin\Resources\AuditLogs\Widgets\AuditActionChart;
use App\Filament\Admin\Resources\AuditLogs\Widgets\AuditLogStatsOverview;
use Filament\Resources\Pages\ListRecords;

final class ListAuditLogs extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = AuditLogResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AuditLogStatsOverview::class,
            AuditActionChart::class,
        ];
    }
}
