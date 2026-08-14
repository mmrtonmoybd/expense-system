<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Tables;

use App\Actions\PostSaleAction;
use App\Actions\RejectSaleAction;
use App\Actions\ReverseSaleAction;
use App\Actions\SubmitSaleAction;
use App\Models\Sale;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SalesTable
{
    public static function configure(Table $table): Table
    {
        $canViewProfit = auth()->user()?->can('ViewProfit') ?? false;
        $canViewCosts = auth()->user()?->can('ViewInternalCosts') ?? false;

        return $table->defaultSort('sale_date', 'desc')->columns([
            TextColumn::make('sale_number')->searchable()->copyable(),
            TextColumn::make('customer.name')->description(fn (Sale $record): string => $record->customer->phone)->searchable(['name', 'phone', 'company_name']),
            TextColumn::make('sale_date')->date()->sortable(),
            TextColumn::make('total_usd')->label('USD')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('total_bdt_revenue')->label('Revenue')->money('BDT')->sortable(),
            ...($canViewCosts ? [TextColumn::make('total_inventory_cost')->label('Cost')->money('BDT')->sortable()] : []),
            ...($canViewProfit ? [TextColumn::make('total_gross_profit')->label('Gross profit')->money('BDT')->sortable()] : []),
            TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                'posted' => 'success', 'pending' => 'warning', 'rejected', 'reversed' => 'danger', default => 'gray'
            }),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Draft', 'pending' => 'Pending', 'posted' => 'Posted', 'rejected' => 'Rejected', 'reversed' => 'Reversed']),
            SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->preload(),
            SelectFilter::make('platform')->relationship('lines.platform', 'name')->searchable()->preload(),
            Filter::make('sale_date')->schema([\Filament\Forms\Components\DatePicker::make('from'), \Filament\Forms\Components\DatePicker::make('until')])->query(fn (Builder $query, array $data): Builder => $query->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('sale_date', '>=', $date))->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('sale_date', '<=', $date))),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make()->visible(fn (Sale $record): bool => in_array($record->status, ['draft', 'pending'], true)),
            Action::make('submit')->label('Submit / Post')->color('success')->requiresConfirmation()->visible(fn (Sale $record): bool => $record->status === 'draft' && auth()->user()->can('Post:Sale'))->action(function (Sale $record): void {
                abort_unless(auth()->user()->can('Post:Sale'), 403);
                app(SubmitSaleAction::class)->execute($record, auth()->user());
                Notification::make()->title('Sale submitted')->success()->send();
            }),
            Action::make('approve')->color('success')->requiresConfirmation()->visible(fn (Sale $record): bool => $record->status === 'pending' && auth()->user()->can('Approve:Sale'))->action(function (Sale $record): void {
                abort_unless(auth()->user()->can('Approve:Sale'), 403);
                $posted = app(PostSaleAction::class)->execute($record, auth()->user());
                app(AuditLogger::class)->record(auth()->user(), 'sale_approved', $posted);
                Notification::make()->title('Sale approved and posted')->success()->send();
            }),
            Action::make('reject')->color('danger')->schema([Textarea::make('reason')->required()])->visible(fn (Sale $record): bool => $record->status === 'pending' && auth()->user()->can('Reject:Sale'))->action(function (Sale $record, array $data): void {
                abort_unless(auth()->user()->can('Reject:Sale'), 403);
                app(RejectSaleAction::class)->execute($record, auth()->user(), $data['reason']);
                Notification::make()->title('Sale rejected')->success()->send();
            }),
            Action::make('invoicePdf')->label('Invoice PDF')->icon('heroicon-o-arrow-down-tray')->visible(fn (Sale $record): bool => $record->status === 'posted')->url(fn (Sale $record): string => route('invoices.pdf', $record->invoice)),
            Action::make('printInvoice')->label('Print invoice')->icon('heroicon-o-printer')->visible(fn (Sale $record): bool => $record->status === 'posted')->url(fn (Sale $record): string => route('invoices.print', $record->invoice))->openUrlInNewTab(),
            Action::make('reverse')->color('danger')->schema([Textarea::make('reason')->required()])->requiresConfirmation()->visible(fn (Sale $record): bool => $record->status === 'posted' && auth()->user()->can('Reverse:Sale'))->action(function (Sale $record, array $data): void {
                abort_unless(auth()->user()->can('Reverse:Sale'), 403);
                app(ReverseSaleAction::class)->execute($record, auth()->user(), $data['reason']);
                Notification::make()->title('Sale reversed')->success()->send();
            }),
        ]);
    }
}
