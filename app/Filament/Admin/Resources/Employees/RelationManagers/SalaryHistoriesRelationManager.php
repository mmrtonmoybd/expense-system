<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class SalaryHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'salaryHistories';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('effective_at')
            ->columns([
                TextColumn::make('old_salary')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('new_salary')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('effective_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('changedBy.name')->label('Changed by'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('effective_at', 'desc');
    }
}
