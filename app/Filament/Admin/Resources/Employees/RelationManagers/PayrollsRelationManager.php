<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use App\Filament\Admin\Resources\Payrolls\PayrollResource;
use App\Models\Payroll;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payroll_number')
            ->defaultSort('payroll_month', 'desc')
            ->columns([
                TextColumn::make('payroll_number')->searchable()->sortable(),
                TextColumn::make('payroll_month')->date('F Y')->sortable(),
                TextColumn::make('base_salary')->money('BDT')->sortable(),
                TextColumn::make('allowance')->money('BDT')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bonus')->money('BDT')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deduction')->money('BDT')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('adjustment')->money('BDT')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_salary_expense')->label('Expense')->money('BDT')->sortable(),
                TextColumn::make('paid_amount')->money('BDT')->sortable(),
                TextColumn::make('due_amount')->money('BDT')->sortable()->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
                TextColumn::make('payment_date')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->multiple()->options(['draft' => 'Draft', 'unpaid' => 'Unpaid', 'partially_paid' => 'Partially paid', 'paid' => 'Paid']),
                DateRangeFilter::make('payroll_month', 'Payroll month'),
                NumberRangeFilter::make('total_salary_expense', 'Salary expense', '৳'),
                NumberRangeFilter::make('due_amount', 'Outstanding', '৳'),
                TernaryFilter::make('has_due')
                    ->label('Has outstanding salary')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('due_amount', '>', 0),
                        false: fn (Builder $query): Builder => $query->where('due_amount', '<=', 0),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->url(fn (Payroll $record): string => PayrollResource::getUrl('view', ['record' => $record])),
                ]),
            ], position: RecordActionsPosition::BeforeColumns);
    }
}
