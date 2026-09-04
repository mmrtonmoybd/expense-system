<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs\Tables;

use App\Filament\Admin\Filters\DateRangeFilter;
use App\Models\AuditLog;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('occurred_at', 'desc')->columns([
            TextColumn::make('occurred_at')->dateTime()->sortable(),
            TextColumn::make('user.name')->label('User')->searchable()->sortable()->placeholder('System'),
            TextColumn::make('action')->badge()->searchable()->sortable()->formatStateUsing(fn (string $state): string => Str::headline($state)),
            TextColumn::make('auditable_type')->label('Model')->formatStateUsing(fn (string $state): string => class_basename($state))->sortable(),
            TextColumn::make('auditable_id')->label('Record')->sortable(),
            TextColumn::make('reason')->limit(50)->wrap()->searchable(),
            TextColumn::make('ip_address')->searchable()->toggleable(),
        ])->filters([
            SelectFilter::make('user_id')->label('User')->relationship('user', 'name')->multiple()->searchable()->preload(),
            SelectFilter::make('action')
                ->label('Action')
                ->multiple()
                ->searchable()
                ->options(fn (): array => AuditLog::query()
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action', 'action')
                    ->map(fn (string $action): string => Str::headline($action))
                    ->all()),
            SelectFilter::make('auditable_type')
                ->label('Model')
                ->multiple()
                ->options(fn (): array => AuditLog::query()
                    ->distinct()
                    ->orderBy('auditable_type')
                    ->pluck('auditable_type', 'auditable_type')
                    ->map(fn (string $type): string => class_basename($type))
                    ->all()),
            SelectFilter::make('ip_address')
                ->label('IP address')
                ->multiple()
                ->searchable()
                ->options(fn (): array => AuditLog::query()->whereNotNull('ip_address')->distinct()->orderBy('ip_address')->pluck('ip_address', 'ip_address')->all()),
            DateRangeFilter::make('occurred_at', 'Occurred', timestamp: true),
            TernaryFilter::make('reason')
                ->label('Has a reason')
                ->nullable()
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('reason'),
                    false: fn (Builder $query): Builder => $query->whereNull('reason'),
                ),
            TernaryFilter::make('changed_values')
                ->label('Records a value change')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('new_values'),
                    false: fn (Builder $query): Builder => $query->whereNull('new_values'),
                ),
        ])->recordActions([
            ActionGroup::make([ViewAction::make()]),
        ], position: RecordActionsPosition::BeforeColumns);
    }
}
