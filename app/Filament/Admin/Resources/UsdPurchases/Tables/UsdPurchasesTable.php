<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Tables;

use App\Actions\PostUsdPurchaseAction;
use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use App\Models\UsdPurchase;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class UsdPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('purchase_date', 'desc')->columns([
            TextColumn::make('purchase_number')->searchable()->copyable()->sortable(),
            TextColumn::make('purchase_date')->date()->sortable(),
            TextColumn::make('usd_quantity')->label('USD')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('purchase_rate')->label('Rate')->money('BDT')->sortable(),
            TextColumn::make('bdt_total')->label('BDT total')->money('BDT')->sortable(),
            TextColumn::make('source')->searchable()->toggleable(),
            TextColumn::make('reference')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('creator.name')->label('Created by')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'posted' ? 'success' : 'gray'),
            TextColumn::make('posted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->multiple()->options(['draft' => 'Draft', 'posted' => 'Posted']),
            SelectFilter::make('source')
                ->label('Source / vendor')
                ->multiple()
                ->searchable()
                ->options(fn (): array => UsdPurchase::query()->whereNotNull('source')->distinct()->orderBy('source')->pluck('source', 'source')->all()),
            SelectFilter::make('created_by')->label('Created by')->relationship('creator', 'name')->multiple()->searchable()->preload(),
            SelectFilter::make('posted_by')->label('Posted by')->relationship('poster', 'name')->multiple()->searchable()->preload(),
            DateRangeFilter::make('purchase_date', 'Purchase date'),
            DateRangeFilter::make('posted_at', 'Posted', timestamp: true),
            NumberRangeFilter::make('usd_quantity', 'USD quantity'),
            NumberRangeFilter::make('purchase_rate', 'Purchase rate', '৳'),
            NumberRangeFilter::make('bdt_total', 'BDT total', '৳'),
            TernaryFilter::make('attachment_path')
                ->label('Has attachment')
                ->nullable()
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('attachment_path'),
                    false: fn (Builder $query): Builder => $query->whereNull('attachment_path'),
                ),
        ])->recordActions([
            ActionGroup::make([
                ViewAction::make(),
                EditAction::make()->visible(fn (UsdPurchase $record): bool => $record->status === 'draft'),
                Action::make('post')->label('Post')->icon(Heroicon::CheckCircle)->color('success')->requiresConfirmation()->visible(fn (UsdPurchase $record): bool => $record->status === 'draft' && auth()->user()->can('Post:UsdPurchase'))->action(function (UsdPurchase $record): void {
                    abort_unless(auth()->user()->can('Post:UsdPurchase'), 403);
                    app(PostUsdPurchaseAction::class)->execute($record, auth()->user());
                    Notification::make()->title('Purchase posted')->success()->send();
                }),
            ]),
        ], position: RecordActionsPosition::BeforeColumns)->toolbarActions([
            BulkActionGroup::make([
                GuardedBulkAction::make(
                    name: 'bulkPost',
                    label: 'Post drafts',
                    icon: Heroicon::CheckCircle,
                    color: 'success',
                    eligible: fn (UsdPurchase $record): bool => $record->status === 'draft',
                    handle: fn (UsdPurchase $record, User $user): mixed => app(PostUsdPurchaseAction::class)->execute($record, $user),
                    ability: 'Post:UsdPurchase',
                    skipReason: 'already posted',
                    confirmation: 'Each draft is added to inventory at its own rate and the weighted average is recalculated.',
                ),
                GuardedBulkAction::make(
                    name: 'bulkDeleteDrafts',
                    label: 'Delete drafts',
                    icon: Heroicon::Trash,
                    color: 'danger',
                    eligible: fn (UsdPurchase $record): bool => $record->status === 'draft',
                    handle: fn (UsdPurchase $record): mixed => $record->delete(),
                    ability: 'DeleteAny:UsdPurchase',
                    skipReason: 'posted purchases cannot be deleted',
                ),
            ]),
        ]);
    }
}
