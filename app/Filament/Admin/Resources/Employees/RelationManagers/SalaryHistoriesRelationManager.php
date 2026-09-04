<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SalaryHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'salaryHistories';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('effective_at')
            ->defaultSort('effective_at', 'desc')
            ->columns([
                TextColumn::make('effective_at')->dateTime()->sortable(),
                TextColumn::make('old_salary')->money('BDT')->placeholder('Initial')->sortable(),
                TextColumn::make('new_salary')->money('BDT')->sortable(),
                TextColumn::make('changedBy.name')->label('Changed by')->searchable(),
                TextColumn::make('reason')->wrap()->searchable(),
            ])
            ->filters([
                SelectFilter::make('changed_by')->label('Changed by')->relationship('changedBy', 'name')->multiple()->searchable()->preload(),
                NumberRangeFilter::make('new_salary', 'New salary', '৳'),
                DateRangeFilter::make('effective_at', 'Effective', timestamp: true),
                TernaryFilter::make('increase')
                    ->label('Salary increased')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereColumn('new_salary', '>', 'old_salary'),
                        false: fn (Builder $query): Builder => $query->whereColumn('new_salary', '<=', 'old_salary'),
                    ),
            ]);
    }
}
