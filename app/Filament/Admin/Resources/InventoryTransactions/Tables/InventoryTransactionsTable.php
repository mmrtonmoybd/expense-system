<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions\Tables;

use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InventoryTransactionsTable
{
    public static function configure(Table $table): Table
    {
        $canViewCosts = auth()->user()?->can('ViewInternalCosts') ?? false;

        return $table->defaultSort('occurred_at', 'desc')->columns([
            TextColumn::make('occurred_at')->dateTime()->sortable(),
            TextColumn::make('transaction_type')->badge()->sortable(),
            TextColumn::make('usd_in')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('usd_out')->numeric(decimalPlaces: 2)->sortable(),
            ...($canViewCosts ? [TextColumn::make('cost_rate')->money('BDT')->sortable()] : []),
            TextColumn::make('resulting_usd_balance')->label('USD balance')->numeric(decimalPlaces: 2)->sortable(),
            ...($canViewCosts ? [
                TextColumn::make('resulting_inventory_value_bdt')->label('Inventory value')->money('BDT')->sortable(),
                TextColumn::make('resulting_weighted_average_cost')->label('Average cost')->money('BDT')->sortable(),
            ] : []),
            TextColumn::make('creator.name')->label('By')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('note')->limit(40)->searchable()->wrap(),
        ])->filters([
            SelectFilter::make('transaction_type')
                ->label('Type')
                ->multiple()
                ->options([
                    'opening_balance' => 'Opening',
                    'purchase' => 'Purchase',
                    'sale' => 'Sale',
                    'positive_adjustment' => 'Positive adjustment',
                    'negative_adjustment' => 'Negative adjustment',
                    'reversal' => 'Reversal',
                ]),
            SelectFilter::make('created_by')->label('Recorded by')->relationship('creator', 'name')->multiple()->searchable()->preload(),
            SelectFilter::make('direction')
                ->label('Direction')
                ->options(['in' => 'Inflow', 'out' => 'Outflow'])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['value'] === 'in', fn (Builder $query): Builder => $query->where('usd_in', '>', 0))
                    ->when($data['value'] === 'out', fn (Builder $query): Builder => $query->where('usd_out', '>', 0))),
            DateRangeFilter::make('occurred_at', 'Occurred', timestamp: true),
            NumberRangeFilter::make('resulting_usd_balance', 'Closing USD balance'),
            ...($canViewCosts ? [NumberRangeFilter::make('cost_rate', 'Cost rate', '৳')] : []),
            TernaryFilter::make('has_note')
                ->label('Has a note')
                ->nullable()
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('note'),
                    false: fn (Builder $query): Builder => $query->whereNull('note'),
                ),
        ])->recordActions([
            ActionGroup::make([ViewAction::make()]),
        ], position: RecordActionsPosition::BeforeColumns);
    }
}
