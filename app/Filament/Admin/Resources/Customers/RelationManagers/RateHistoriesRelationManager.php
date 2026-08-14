<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class RateHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'rateHistories';

    public function table(Table $table): Table
    {
        return $table->defaultSort('effective_at', 'desc')->columns([
            TextColumn::make('platform.name')->label('Platform'),
            TextColumn::make('old_rate')->money('BDT')->placeholder('Initial'),
            TextColumn::make('new_rate')->money('BDT'),
            TextColumn::make('changedBy.name')->label('Changed by'),
            TextColumn::make('reason')->wrap(),
            TextColumn::make('effective_at')->dateTime()->sortable(),
        ]);
    }
}
