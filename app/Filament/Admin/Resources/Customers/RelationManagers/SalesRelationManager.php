<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\RelationManagers;

use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->with('invoice'))->defaultSort('sale_date', 'desc')->columns([
            TextColumn::make('sale_number')->searchable(),
            TextColumn::make('sale_date')->date()->sortable(),
            TextColumn::make('total_usd')->label('USD')->numeric(decimalPlaces: 2),
            TextColumn::make('total_bdt_revenue')->label('Revenue')->money('BDT'),
            TextColumn::make('total_gross_profit')->label('Profit')->money('BDT')->visible(fn (): bool => auth()->user()->can('ViewProfit')),
            TextColumn::make('status')->badge(),
        ])->recordActions([
            ViewAction::make()->url(fn (Sale $record): string => SaleResource::getUrl('view', ['record' => $record])),
            Action::make('invoicePdf')->label('Invoice PDF')->icon('heroicon-o-arrow-down-tray')->visible(fn (Sale $record): bool => $record->status === 'posted' && $record->invoice !== null)->url(fn (Sale $record): string => route('invoices.pdf', $record->invoice)),
            Action::make('printInvoice')->label('Print')->icon('heroicon-o-printer')->visible(fn (Sale $record): bool => $record->status === 'posted' && $record->invoice !== null)->url(fn (Sale $record): string => route('invoices.print', $record->invoice))->openUrlInNewTab(),
        ]);
    }
}
