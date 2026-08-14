<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('name')->searchable()->sortable()->description(fn ($record): ?string => $record->company_name),
            TextColumn::make('phone')->searchable()->copyable(),
            TextColumn::make('email')->searchable()->toggleable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
            TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([TernaryFilter::make('is_active')->label('Active')])->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
