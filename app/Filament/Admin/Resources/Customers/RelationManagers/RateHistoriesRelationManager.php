<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\RelationManagers;

use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
        ])->filters([
            SelectFilter::make('platform_id')->label('Platform')->relationship('platform', 'name')->multiple()->searchable()->preload(),
            SelectFilter::make('changed_by')->label('Changed by')->relationship('changedBy', 'name')->multiple()->searchable()->preload(),
            NumberRangeFilter::make('new_rate', 'New rate', '৳'),
            DateRangeFilter::make('effective_at', 'Effective', timestamp: true),
            TernaryFilter::make('increase')
                ->label('Rate increased')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereColumn('new_rate', '>', 'old_rate'),
                    false: fn (Builder $query): Builder => $query->whereColumn('new_rate', '<=', 'old_rate'),
                ),
        ]);
    }
}
