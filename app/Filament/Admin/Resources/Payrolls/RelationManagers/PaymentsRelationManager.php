<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\RelationManagers;

use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use App\Models\PayrollPayment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment_date')
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('amount')->money('BDT')->sortable(),
                TextColumn::make('method')->badge()->color('gray')->searchable()->placeholder('-'),
                TextColumn::make('reference')->searchable()->placeholder('-'),
                TextColumn::make('creator.name')->label('Recorded by')->searchable(),
                TextColumn::make('note')->wrap()->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Method')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => PayrollPayment::query()->whereNotNull('method')->distinct()->orderBy('method')->pluck('method', 'method')->all()),
                SelectFilter::make('created_by')->label('Recorded by')->relationship('creator', 'name')->multiple()->searchable()->preload(),
                DateRangeFilter::make('payment_date', 'Payment date'),
                NumberRangeFilter::make('amount', 'Amount', '৳'),
                TernaryFilter::make('reference')
                    ->label('Has a reference')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('reference'),
                        false: fn (Builder $query): Builder => $query->whereNull('reference'),
                    ),
            ]);
    }
}
