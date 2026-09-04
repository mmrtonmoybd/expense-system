<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Tables;

use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\AuditLogger;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('expense_date', 'desc')->columns([
            TextColumn::make('expense_number')->searchable()->copyable()->sortable(),
            TextColumn::make('expense_date')->date()->sortable(),
            TextColumn::make('category.name')->label('Category')->badge()->color('gray')->sortable(),
            TextColumn::make('amount')->money('BDT')->sortable(),
            TextColumn::make('description')->limit(45)->searchable()->wrap(),
            TextColumn::make('vendor')->searchable()->toggleable(),
            TextColumn::make('reference')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('creator.name')->label('Created by')->toggleable(),
        ])->filters([
            SelectFilter::make('expense_category_id')->label('Category')->relationship('category', 'name')->multiple()->searchable()->preload(),
            SelectFilter::make('created_by')->label('Created by')->relationship('creator', 'name')->multiple()->searchable()->preload(),
            SelectFilter::make('vendor')
                ->label('Vendor')
                ->multiple()
                ->searchable()
                ->options(fn (): array => Expense::query()->whereNotNull('vendor')->distinct()->orderBy('vendor')->pluck('vendor', 'vendor')->all()),
            DateRangeFilter::make('expense_date', 'Expense date'),
            NumberRangeFilter::make('amount', 'Amount', '৳'),
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
                EditAction::make(),
            ]),
        ], position: RecordActionsPosition::BeforeColumns)->toolbarActions([
            BulkActionGroup::make([
                GuardedBulkAction::make(
                    name: 'bulkRecategorise',
                    label: 'Move to another category',
                    icon: Heroicon::Tag,
                    color: 'warning',
                    eligible: fn (Expense $record): bool => true,
                    handle: function (Expense $record, User $user, array $data): void {
                        $from = (string) $record->expense_category_id;
                        $record->update(['expense_category_id' => (int) $data['expense_category_id']]);
                        app(AuditLogger::class)->record($user, 'expense_recategorised', $record, ['expense_category_id' => $from], ['expense_category_id' => $data['expense_category_id']]);
                    },
                    ability: 'Update:Expense',
                    schema: [
                        Select::make('expense_category_id')
                            ->label('New category')
                            ->options(fn (): array => ExpenseCategory::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ],
                ),
            ]),
        ]);
    }
}
