<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Admin\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\Admin\Resources\AuditLogs\Schemas\AuditLogInfolist;
use App\Filament\Admin\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Filament\Admin\Resources\AuditLogs\Widgets\AuditActionChart;
use App\Filament\Admin\Resources\AuditLogs\Widgets\AuditLogStatsOverview;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('panel.resources.audit_log.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.audit_log.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Administration');
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuditLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [
            AuditLogStatsOverview::class,
            AuditActionChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}
