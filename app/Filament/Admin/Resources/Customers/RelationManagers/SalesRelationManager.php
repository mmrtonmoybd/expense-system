<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\RelationManagers;

use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Models\Platform;
use App\Models\Sale;
use App\Support\CostingMethod;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    public function table(Table $table): Table
    {
        $canViewProfit = auth()->user()?->can('ViewProfit') ?? false;

        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->with('invoice'))->defaultSort('sale_date', 'desc')->columns([
            TextColumn::make('sale_number')->searchable()->sortable(),
            TextColumn::make('sale_date')->date()->sortable(),
            TextColumn::make('total_usd')->label('USD')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('total_bdt_revenue')->label('Revenue')->money('BDT')->sortable(),
            ...($canViewProfit ? [TextColumn::make('total_gross_profit')->label('Profit')->money('BDT')->sortable()] : []),
            TextColumn::make('invoice.invoice_number')->label('Invoice')->searchable()->toggleable(),
            TextColumn::make('status')->badge(),
        ])->filters([
            SelectFilter::make('status')->multiple()->options(['draft' => 'Draft', 'pending' => 'Pending', 'posted' => 'Posted', 'rejected' => 'Rejected', 'reversed' => 'Reversed']),
            SelectFilter::make('costing_method')->label('Costing method')->multiple()->options(CostingMethod::options()),
            SelectFilter::make('platform')
                ->label('Platform')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => Platform::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['values'] ?? []),
                    fn (Builder $query): Builder => $query->whereHas('lines', fn (Builder $lines): Builder => $lines->whereIn('platform_id', $data['values'])),
                )),
            DateRangeFilter::make('sale_date', 'Sale date'),
            NumberRangeFilter::make('total_usd', 'USD quantity'),
            NumberRangeFilter::make('total_bdt_revenue', 'Revenue', '৳'),
            TernaryFilter::make('has_invoice')
                ->label('Invoiced')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereHas('invoice'),
                    false: fn (Builder $query): Builder => $query->whereDoesntHave('invoice'),
                ),
        ])->recordActions([
            ActionGroup::make([
                ViewAction::make()->url(fn (Sale $record): string => SaleResource::getUrl('view', ['record' => $record])),
                Action::make('invoicePdf')->label('Invoice PDF')->icon(Heroicon::ArrowDownTray)->visible(fn (Sale $record): bool => $record->status === 'posted' && $record->invoice !== null)->url(fn (Sale $record): string => route('invoices.pdf', $record->invoice)),
                Action::make('printInvoice')->label('Print')->icon(Heroicon::Printer)->visible(fn (Sale $record): bool => $record->status === 'posted' && $record->invoice !== null)->url(fn (Sale $record): string => route('invoices.print', $record->invoice))->openUrlInNewTab(),
            ]),
        ], position: RecordActionsPosition::BeforeColumns);
    }
}
