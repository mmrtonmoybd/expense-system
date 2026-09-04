<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Tables;

use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use App\Models\Employee;
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

final class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        $canViewPayroll = auth()->user()?->can('ViewPayroll') ?? false;

        return $table->defaultSort('name')->columns([
            TextColumn::make('employee_code')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable()->description(fn ($record): string => $record->designation),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ...($canViewPayroll ? [TextColumn::make('current_salary')->money('BDT')->sortable()] : []),
            TextColumn::make('joining_date')->date()->sortable(),
            TextColumn::make('payrolls_count')->label('Payrolls')->counts('payrolls')->sortable()->toggleable(),
            IconColumn::make('is_active')->boolean(),
        ])->filters([
            TernaryFilter::make('is_active')->label('Active'),
            SelectFilter::make('designation')
                ->label('Designation')
                ->multiple()
                ->searchable()
                ->options(fn (): array => Employee::query()->whereNotNull('designation')->distinct()->orderBy('designation')->pluck('designation', 'designation')->all()),
            TernaryFilter::make('user_id')
                ->label('Linked to a login')
                ->nullable()
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                    false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                ),
            TernaryFilter::make('salary_due')
                ->label('Has unpaid salary')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereHas('payrolls', fn (Builder $payrolls): Builder => $payrolls->where('due_amount', '>', 0)),
                    false: fn (Builder $query): Builder => $query->whereDoesntHave('payrolls', fn (Builder $payrolls): Builder => $payrolls->where('due_amount', '>', 0)),
                ),
            DateRangeFilter::make('joining_date', 'Joined'),
            ...($canViewPayroll ? [NumberRangeFilter::make('current_salary', 'Current salary', '৳')] : []),
        ])->recordActions([
            ActionGroup::make([
                ViewAction::make(),
                EditAction::make(),
            ]),
        ], position: RecordActionsPosition::BeforeColumns)->toolbarActions([
            BulkActionGroup::make([
                GuardedBulkAction::make(
                    name: 'bulkActivate',
                    label: 'Mark as active',
                    icon: Heroicon::CheckCircle,
                    color: 'success',
                    eligible: fn (Employee $record): bool => ! $record->is_active,
                    handle: fn (Employee $record): mixed => $record->update(['is_active' => true]),
                    ability: 'Update:Employee',
                    skipReason: 'already active',
                ),
                GuardedBulkAction::make(
                    name: 'bulkDeactivate',
                    label: 'Mark as left',
                    icon: Heroicon::NoSymbol,
                    color: 'warning',
                    eligible: fn (Employee $record): bool => (bool) $record->is_active,
                    handle: fn (Employee $record): mixed => $record->update(['is_active' => false]),
                    ability: 'Update:Employee',
                    skipReason: 'already inactive',
                ),
                GuardedBulkAction::make(
                    name: 'bulkDelete',
                    label: 'Delete',
                    icon: Heroicon::Trash,
                    color: 'danger',
                    eligible: fn (Employee $record): bool => ! $record->payrolls()->exists(),
                    handle: fn (Employee $record): mixed => $record->delete(),
                    ability: 'DeleteAny:Employee',
                    skipReason: 'has payroll on record',
                ),
            ]),
        ]);
    }
}
