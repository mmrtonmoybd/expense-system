<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Tables;

use App\Actions\PostSaleAction;
use App\Actions\RejectSaleAction;
use App\Actions\ReverseSaleAction;
use App\Actions\SubmitSaleAction;
use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CostingMethod;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SalesTable
{
    public static function configure(Table $table): Table
    {
        $canViewProfit = auth()->user()?->can('ViewProfit') ?? false;
        $canViewCosts = auth()->user()?->can('ViewInternalCosts') ?? false;

        return $table->defaultSort('sale_date', 'desc')->columns([
            TextColumn::make('sale_number')->searchable()->copyable()->sortable(),
            TextColumn::make('customer.name')->description(fn (Sale $record): string => $record->customer->phone)->searchable(['name', 'phone', 'company_name'])->sortable(),
            TextColumn::make('sale_date')->date()->sortable(),
            TextColumn::make('total_usd')->label('USD')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('total_bdt_revenue')->label('Revenue')->money('BDT')->sortable(),
            ...($canViewCosts ? [TextColumn::make('total_inventory_cost')->label('Cost')->money('BDT')->sortable()] : []),
            ...($canViewProfit ? [TextColumn::make('total_gross_profit')->label('Gross profit')->money('BDT')->sortable()] : []),
            ...($canViewCosts ? [TextColumn::make('costing_method')->label('Costing')->badge()->color('gray')->formatStateUsing(fn (CostingMethod $state): string => $state->shortLabel())->placeholder('-')->toggleable()] : []),
            TextColumn::make('invoice.invoice_number')->label('Invoice')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('creator.name')->label('Created by')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('posted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                'posted' => 'success', 'pending' => 'warning', 'rejected', 'reversed' => 'danger', default => 'gray'
            }),
        ])->filters([
            SelectFilter::make('status')->multiple()->options(['draft' => 'Draft', 'pending' => 'Pending', 'posted' => 'Posted', 'rejected' => 'Rejected', 'reversed' => 'Reversed']),
            SelectFilter::make('approval_status')->label('Approval')->multiple()->options(['not_required' => 'Not required', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            SelectFilter::make('costing_method')->label('Costing method')->multiple()->options(CostingMethod::options()),
            SelectFilter::make('customer_id')->label('Customer')->relationship('customer', 'name')->multiple()->searchable()->preload(),
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
            SelectFilter::make('created_by')->label('Created by')->relationship('creator', 'name')->multiple()->searchable()->preload(),
            DateRangeFilter::make('sale_date', 'Sale date'),
            DateRangeFilter::make('posted_at', 'Posted', timestamp: true),
            NumberRangeFilter::make('total_usd', 'USD quantity'),
            NumberRangeFilter::make('total_bdt_revenue', 'Revenue', '৳'),
            ...($canViewProfit ? [NumberRangeFilter::make('total_gross_profit', 'Gross profit', '৳')] : []),
            TernaryFilter::make('has_invoice')
                ->label('Invoiced')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereHas('invoice'),
                    false: fn (Builder $query): Builder => $query->whereDoesntHave('invoice'),
                ),
            TernaryFilter::make('rate_overridden')
                ->label('Rate overridden')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereHas('lines', fn (Builder $lines): Builder => $lines->where('is_rate_overridden', true)),
                    false: fn (Builder $query): Builder => $query->whereDoesntHave('lines', fn (Builder $lines): Builder => $lines->where('is_rate_overridden', true)),
                ),
            ...($canViewProfit ? [TernaryFilter::make('loss_making')
                ->label('Loss making')
                ->queries(
                    true: fn (Builder $query): Builder => $query->where('total_gross_profit', '<', 0),
                    false: fn (Builder $query): Builder => $query->where('total_gross_profit', '>=', 0),
                )] : []),
        ])->recordActions([
            ActionGroup::make([
                ViewAction::make(),
                EditAction::make()->visible(fn (Sale $record): bool => in_array($record->status, ['draft', 'pending'], true)),
                Action::make('submit')->label('Submit / Post')->icon(Heroicon::PaperAirplane)->color('success')->requiresConfirmation()->visible(fn (Sale $record): bool => $record->status === 'draft' && auth()->user()->can('Post:Sale'))->action(function (Sale $record): void {
                    abort_unless(auth()->user()->can('Post:Sale'), 403);
                    app(SubmitSaleAction::class)->execute($record, auth()->user());
                    Notification::make()->title('Sale submitted')->success()->send();
                }),
                Action::make('approve')->icon(Heroicon::CheckCircle)->color('success')->requiresConfirmation()->visible(fn (Sale $record): bool => $record->status === 'pending' && auth()->user()->can('Approve:Sale'))->action(function (Sale $record): void {
                    abort_unless(auth()->user()->can('Approve:Sale'), 403);
                    $posted = app(PostSaleAction::class)->execute($record, auth()->user());
                    app(AuditLogger::class)->record(auth()->user(), 'sale_approved', $posted);
                    Notification::make()->title('Sale approved and posted')->success()->send();
                }),
                Action::make('reject')->icon(Heroicon::XCircle)->color('danger')->schema([Textarea::make('reason')->required()])->visible(fn (Sale $record): bool => $record->status === 'pending' && auth()->user()->can('Reject:Sale'))->action(function (Sale $record, array $data): void {
                    abort_unless(auth()->user()->can('Reject:Sale'), 403);
                    app(RejectSaleAction::class)->execute($record, auth()->user(), $data['reason']);
                    Notification::make()->title('Sale rejected')->success()->send();
                }),
                Action::make('invoicePdf')->label('Invoice PDF')->icon(Heroicon::ArrowDownTray)->visible(fn (Sale $record): bool => $record->status === 'posted')->url(fn (Sale $record): string => route('invoices.pdf', $record->invoice)),
                Action::make('printInvoice')->label('Print invoice')->icon(Heroicon::Printer)->visible(fn (Sale $record): bool => $record->status === 'posted')->url(fn (Sale $record): string => route('invoices.print', $record->invoice))->openUrlInNewTab(),
                Action::make('reverse')->icon(Heroicon::ArrowUturnLeft)->color('danger')->schema([Textarea::make('reason')->required()])->requiresConfirmation()->visible(fn (Sale $record): bool => $record->status === 'posted' && auth()->user()->can('Reverse:Sale'))->action(function (Sale $record, array $data): void {
                    abort_unless(auth()->user()->can('Reverse:Sale'), 403);
                    app(ReverseSaleAction::class)->execute($record, auth()->user(), $data['reason']);
                    Notification::make()->title('Sale reversed')->success()->send();
                }),
            ]),
        ], position: RecordActionsPosition::BeforeColumns)->toolbarActions([
            BulkActionGroup::make([
                GuardedBulkAction::make(
                    name: 'bulkSubmit',
                    label: 'Submit / post drafts',
                    icon: Heroicon::PaperAirplane,
                    color: 'success',
                    eligible: fn (Sale $record): bool => $record->status === 'draft',
                    handle: fn (Sale $record, User $user): mixed => app(SubmitSaleAction::class)->execute($record, $user),
                    ability: 'Post:Sale',
                    skipReason: 'not a draft',
                    confirmation: 'Drafts are costed against inventory one after another, in the order they appear.',
                ),
                GuardedBulkAction::make(
                    name: 'bulkApprove',
                    label: 'Approve pending',
                    icon: Heroicon::CheckCircle,
                    color: 'success',
                    eligible: fn (Sale $record): bool => $record->status === 'pending',
                    handle: function (Sale $record, User $user): void {
                        $posted = app(PostSaleAction::class)->execute($record, $user);
                        app(AuditLogger::class)->record($user, 'sale_approved', $posted);
                    },
                    ability: 'Approve:Sale',
                    skipReason: 'not pending',
                ),
                GuardedBulkAction::make(
                    name: 'bulkReject',
                    label: 'Reject pending',
                    icon: Heroicon::XCircle,
                    color: 'danger',
                    eligible: fn (Sale $record): bool => $record->status === 'pending',
                    handle: fn (Sale $record, User $user, array $data): mixed => app(RejectSaleAction::class)->execute($record, $user, (string) $data['reason']),
                    ability: 'Reject:Sale',
                    skipReason: 'not pending',
                    schema: [Textarea::make('reason')->label('Rejection reason')->required()],
                ),
                GuardedBulkAction::make(
                    name: 'bulkReverse',
                    label: 'Reverse posted',
                    icon: Heroicon::ArrowUturnLeft,
                    color: 'danger',
                    eligible: fn (Sale $record): bool => $record->status === 'posted',
                    handle: fn (Sale $record, User $user, array $data): mixed => app(ReverseSaleAction::class)->execute($record, $user, (string) $data['reason']),
                    ability: 'Reverse:Sale',
                    skipReason: 'not posted',
                    schema: [Textarea::make('reason')->label('Reversal reason')->required()],
                    confirmation: 'Each reversal returns its dollars to inventory and cancels the invoice.',
                ),
                GuardedBulkAction::make(
                    name: 'bulkDeleteDrafts',
                    label: 'Delete drafts',
                    icon: Heroicon::Trash,
                    color: 'danger',
                    eligible: fn (Sale $record): bool => $record->status === 'draft',
                    handle: fn (Sale $record): mixed => $record->delete(),
                    ability: 'DeleteAny:Sale',
                    skipReason: 'only drafts can be deleted',
                ),
            ]),
        ]);
    }
}
