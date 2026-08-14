<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Tables;

use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('invoice_date', 'desc')->columns([
            TextColumn::make('invoice_number')->searchable()->copyable(),
            TextColumn::make('sale.sale_number')->label('Sale')->searchable(),
            TextColumn::make('sale.customer.name')->label('Customer')->description(fn (Invoice $record): string => $record->sale->customer->phone)->searchable(['name', 'phone']),
            TextColumn::make('invoice_date')->date()->sortable(),
            TextColumn::make('sale.total_usd')->label('USD')->numeric(decimalPlaces: 2),
            TextColumn::make('sale.total_bdt_revenue')->label('Total')->money('BDT'),
            TextColumn::make('status')->badge(),
        ])->filters([SelectFilter::make('status')->options(['issued' => 'Issued', 'cancelled' => 'Cancelled'])])->recordActions([
            ViewAction::make(),
            Action::make('pdf')->label('PDF')->icon('heroicon-o-arrow-down-tray')->url(fn (Invoice $record): string => route('invoices.pdf', $record)),
            Action::make('print')->icon('heroicon-o-printer')->url(fn (Invoice $record): string => route('invoices.print', $record))->openUrlInNewTab(),
        ]);
    }
}
