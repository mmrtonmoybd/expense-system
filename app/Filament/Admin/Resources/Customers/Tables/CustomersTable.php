<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Tables;

use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Models\Customer;
use App\Models\Platform;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('name')->searchable()->sortable()->description(fn ($record): ?string => $record->company_name),
            TextColumn::make('phone')->searchable()->copyable(),
            TextColumn::make('email')->searchable()->toggleable(),
            TextColumn::make('customer_platforms_count')->label('Platforms')->counts('customerPlatforms')->sortable()->toggleable(),
            TextColumn::make('sales_count')->label('Sales')->counts('sales')->sortable()->toggleable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
            TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            TernaryFilter::make('is_active')->label('Active'),
            SelectFilter::make('platform')
                ->label('Assigned platform')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => Platform::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['values'] ?? []),
                    fn (Builder $query): Builder => $query->whereHas('customerPlatforms', fn (Builder $assignments): Builder => $assignments->whereIn('platform_id', $data['values'])),
                )),
            TernaryFilter::make('has_sales')
                ->label('Has posted sales')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereHas('sales', fn (Builder $sales): Builder => $sales->where('status', 'posted')),
                    false: fn (Builder $query): Builder => $query->whereDoesntHave('sales', fn (Builder $sales): Builder => $sales->where('status', 'posted')),
                ),
            TernaryFilter::make('company_name')
                ->label('Is a company')
                ->nullable()
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('company_name'),
                    false: fn (Builder $query): Builder => $query->whereNull('company_name'),
                ),
            DateRangeFilter::make('created_at', 'Added', timestamp: true),
        ])->recordActions([
            ActionGroup::make([
                ViewAction::make(),
                EditAction::make(),
            ]),
        ], position: RecordActionsPosition::BeforeColumns)->toolbarActions([
            BulkActionGroup::make([
                GuardedBulkAction::make(
                    name: 'bulkActivate',
                    label: 'Activate',
                    icon: Heroicon::CheckCircle,
                    color: 'success',
                    eligible: fn (Customer $record): bool => ! $record->is_active,
                    handle: fn (Customer $record): mixed => $record->update(['is_active' => true]),
                    ability: 'Update:Customer',
                    skipReason: 'already active',
                ),
                GuardedBulkAction::make(
                    name: 'bulkDeactivate',
                    label: 'Deactivate',
                    icon: Heroicon::NoSymbol,
                    color: 'warning',
                    eligible: fn (Customer $record): bool => (bool) $record->is_active,
                    handle: fn (Customer $record): mixed => $record->update(['is_active' => false]),
                    ability: 'Update:Customer',
                    skipReason: 'already inactive',
                ),
                GuardedBulkAction::make(
                    name: 'bulkDelete',
                    label: 'Delete',
                    icon: Heroicon::Trash,
                    color: 'danger',
                    eligible: fn (Customer $record): bool => ! $record->sales()->exists(),
                    handle: fn (Customer $record): mixed => $record->delete(),
                    ability: 'DeleteAny:Customer',
                    skipReason: 'has sales on record',
                    confirmation: 'Customers with sales are kept: their trading history has to stay intact.',
                ),
            ]),
        ]);
    }
}
