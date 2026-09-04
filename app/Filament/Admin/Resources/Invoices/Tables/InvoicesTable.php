<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Tables;

use App\Filament\Admin\Filters\DateRangeFilter;
use App\Models\Customer;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('invoice_date', 'desc')->columns([
            TextColumn::make('invoice_number')->searchable()->copyable()->sortable(),
            TextColumn::make('sale.sale_number')->label('Sale')->searchable(),
            TextColumn::make('sale.customer.name')->label('Customer')->description(fn (Invoice $record): string => $record->sale->customer->phone)->searchable(['name', 'phone']),
            TextColumn::make('invoice_date')->date()->sortable(),
            TextColumn::make('sale.total_usd')->label('USD')->numeric(decimalPlaces: 2),
            TextColumn::make('sale.total_bdt_revenue')->label('Total')->money('BDT'),
            TextColumn::make('cancelled_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'issued' ? 'success' : 'danger'),
        ])->filters([
            SelectFilter::make('status')->multiple()->options(['issued' => 'Issued', 'cancelled' => 'Cancelled']),
            SelectFilter::make('customer')
                ->label('Customer')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['values'] ?? []),
                    fn (Builder $query): Builder => $query->whereHas('sale', fn (Builder $sale): Builder => $sale->whereIn('customer_id', $data['values'])),
                )),
            SelectFilter::make('costing_method')
                ->label('Costing method')
                ->multiple()
                ->options(['average' => 'Moving Weighted Average', 'fifo' => 'FIFO', 'lifo' => 'LIFO'])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['values'] ?? []),
                    fn (Builder $query): Builder => $query->whereHas('sale', fn (Builder $sale): Builder => $sale->whereIn('costing_method', $data['values'])),
                )),
            DateRangeFilter::make('invoice_date', 'Invoice date'),
            TernaryFilter::make('cancelled_at')
                ->label('Cancelled')
                ->nullable()
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('cancelled_at'),
                    false: fn (Builder $query): Builder => $query->whereNull('cancelled_at'),
                ),
        ])->recordActions([
            ActionGroup::make([
                ViewAction::make(),
                Action::make('pdf')->label('PDF')->icon(Heroicon::ArrowDownTray)->url(fn (Invoice $record): string => route('invoices.pdf', $record)),
                Action::make('print')->icon(Heroicon::Printer)->url(fn (Invoice $record): string => route('invoices.print', $record))->openUrlInNewTab(),
            ]),
        ], position: RecordActionsPosition::BeforeColumns);
    }
}
