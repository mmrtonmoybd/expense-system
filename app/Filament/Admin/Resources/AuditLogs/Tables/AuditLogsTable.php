<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('occurred_at', 'desc')->columns([TextColumn::make('occurred_at')->dateTime()->sortable(), TextColumn::make('user.name')->label('User')->searchable(), TextColumn::make('action')->badge()->searchable(), TextColumn::make('auditable_type')->label('Model')->formatStateUsing(fn (string $state): string => class_basename($state)), TextColumn::make('auditable_id')->label('Record'), TextColumn::make('reason')->limit(50)->wrap(), TextColumn::make('ip_address')->toggleable()])->filters([SelectFilter::make('user_id')->relationship('user', 'name')->searchable()->preload()])->recordActions([ViewAction::make()]);
    }
}
