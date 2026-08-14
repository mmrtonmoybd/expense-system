<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InventoryTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('occurred_at', 'desc')->columns([TextColumn::make('occurred_at')->dateTime()->sortable(), TextColumn::make('transaction_type')->badge(), TextColumn::make('usd_in')->numeric(decimalPlaces: 2), TextColumn::make('usd_out')->numeric(decimalPlaces: 2), TextColumn::make('cost_rate')->money('BDT')->visible(fn (): bool => auth()->user()->can('ViewInternalCosts')), TextColumn::make('resulting_usd_balance')->label('USD balance')->numeric(decimalPlaces: 2), TextColumn::make('resulting_inventory_value_bdt')->label('Inventory value')->money('BDT')->visible(fn (): bool => auth()->user()->can('ViewInternalCosts')), TextColumn::make('resulting_weighted_average_cost')->label('Average cost')->money('BDT')->visible(fn (): bool => auth()->user()->can('ViewInternalCosts')), TextColumn::make('note')->limit(40)])->filters([SelectFilter::make('transaction_type')->options(['opening_balance' => 'Opening', 'purchase' => 'Purchase', 'sale' => 'Sale', 'positive_adjustment' => 'Positive adjustment', 'negative_adjustment' => 'Negative adjustment', 'reversal' => 'Reversal'])])->recordActions([ViewAction::make()]);
    }
}
