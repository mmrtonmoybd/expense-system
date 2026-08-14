<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Tables;

use App\Actions\PostUsdPurchaseAction;
use App\Models\UsdPurchase;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class UsdPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('purchase_date', 'desc')->columns([
            TextColumn::make('purchase_number')->searchable()->copyable(),
            TextColumn::make('purchase_date')->date()->sortable(),
            TextColumn::make('usd_quantity')->label('USD')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('purchase_rate')->label('Rate')->money('BDT')->sortable(),
            TextColumn::make('bdt_total')->label('BDT total')->money('BDT')->sortable(),
            TextColumn::make('source')->searchable()->toggleable(),
            TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'posted' ? 'success' : 'gray'),
            TextColumn::make('posted_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'posted' => 'Posted'])])->recordActions([
            ViewAction::make(),
            EditAction::make()->visible(fn (UsdPurchase $record): bool => $record->status === 'draft'),
            Action::make('post')->label('Post')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()->visible(fn (UsdPurchase $record): bool => $record->status === 'draft' && auth()->user()->can('Post:UsdPurchase'))->action(function (UsdPurchase $record): void {
                abort_unless(auth()->user()->can('Post:UsdPurchase'), 403);
                app(PostUsdPurchaseAction::class)->execute($record, auth()->user());
                Notification::make()->title('Purchase posted')->success()->send();
            }),
        ]);
    }
}
