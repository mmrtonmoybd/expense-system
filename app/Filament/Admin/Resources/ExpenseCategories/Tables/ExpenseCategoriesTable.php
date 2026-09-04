<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseCategories\Tables;

use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Models\ExpenseCategory;
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

final class ExpenseCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('expenses_count')->label('Entries')->counts('expenses')->sortable()->toggleable(),
                TextColumn::make('expenses_sum_amount')->label('Total spent')->sum('expenses', 'amount')->money('BDT')->sortable()->toggleable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->numeric()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('has_expenses')
                    ->label('Has expenses')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('expenses'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('expenses'),
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
                        eligible: fn (ExpenseCategory $record): bool => ! $record->is_active,
                        handle: fn (ExpenseCategory $record): mixed => $record->update(['is_active' => true]),
                        ability: 'Update:ExpenseCategory',
                        skipReason: 'already active',
                    ),
                    GuardedBulkAction::make(
                        name: 'bulkDeactivate',
                        label: 'Deactivate',
                        icon: Heroicon::NoSymbol,
                        color: 'warning',
                        eligible: fn (ExpenseCategory $record): bool => (bool) $record->is_active,
                        handle: fn (ExpenseCategory $record): mixed => $record->update(['is_active' => false]),
                        ability: 'Update:ExpenseCategory',
                        skipReason: 'already inactive',
                    ),
                    GuardedBulkAction::make(
                        name: 'bulkDelete',
                        label: 'Delete',
                        icon: Heroicon::Trash,
                        color: 'danger',
                        eligible: fn (ExpenseCategory $record): bool => ! $record->expenses()->exists(),
                        handle: fn (ExpenseCategory $record): mixed => $record->delete(),
                        ability: 'DeleteAny:ExpenseCategory',
                        skipReason: 'expenses are booked against it',
                    ),
                ]),
            ]);
    }
}
