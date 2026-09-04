<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Platforms\Tables;

use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Models\Platform;
use App\Models\SaleLine;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PlatformsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('short_name')->searchable()->sortable(),
                TextColumn::make('customer_platforms_count')->label('Customers')->counts('customerPlatforms')->sortable()->toggleable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->numeric()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('has_customers')
                    ->label('Assigned to a customer')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('customerPlatforms'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('customerPlatforms'),
                    ),
                TernaryFilter::make('logo_path')
                    ->label('Has a logo')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('logo_path'),
                        false: fn (Builder $query): Builder => $query->whereNull('logo_path'),
                    ),
                DateRangeFilter::make('created_at', 'Added', timestamp: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    GuardedBulkAction::make(
                        name: 'bulkActivate',
                        label: 'Activate',
                        icon: Heroicon::CheckCircle,
                        color: 'success',
                        eligible: fn (Platform $record): bool => ! $record->is_active,
                        handle: fn (Platform $record): mixed => $record->update(['is_active' => true]),
                        ability: 'Update:Platform',
                        skipReason: 'already active',
                    ),
                    GuardedBulkAction::make(
                        name: 'bulkDeactivate',
                        label: 'Deactivate',
                        icon: Heroicon::NoSymbol,
                        color: 'warning',
                        eligible: fn (Platform $record): bool => (bool) $record->is_active,
                        handle: fn (Platform $record): mixed => $record->update(['is_active' => false]),
                        ability: 'Update:Platform',
                        skipReason: 'already inactive',
                    ),
                    GuardedBulkAction::make(
                        name: 'bulkDelete',
                        label: 'Delete',
                        icon: Heroicon::Trash,
                        color: 'danger',
                        eligible: fn (Platform $record): bool => ! $record->customerPlatforms()->exists()
                            && ! SaleLine::query()->where('platform_id', $record->getKey())->exists(),
                        handle: fn (Platform $record): mixed => $record->delete(),
                        ability: 'DeleteAny:Platform',
                        skipReason: 'assigned to a customer or used on a sale',
                    ),
                ]),
            ]);
    }
}
