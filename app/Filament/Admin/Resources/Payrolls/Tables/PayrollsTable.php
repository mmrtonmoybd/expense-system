<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Tables;

use App\Actions\RecordPayrollPaymentAction;
use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Filament\Admin\Filters\NumberRangeFilter;
use App\Models\Payroll;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PayrollsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('payroll_month', 'desc')->columns([
            TextColumn::make('payroll_number')->searchable()->sortable(),
            TextColumn::make('employee.name')->searchable()->sortable()->description(fn (Payroll $record): ?string => $record->employee?->designation),
            TextColumn::make('payroll_month')->date('F Y')->sortable(),
            TextColumn::make('total_salary_expense')->label('Expense')->money('BDT')->sortable(),
            TextColumn::make('paid_amount')->money('BDT')->sortable(),
            TextColumn::make('due_amount')->money('BDT')->sortable()->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
            TextColumn::make('payments_count')->label('Payments')->counts('payments')->toggleable(),
            TextColumn::make('payment_date')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')->badge(),
        ])->filters([
            SelectFilter::make('employee_id')->label('Employee')->relationship('employee', 'name')->multiple()->searchable()->preload(),
            SelectFilter::make('status')->multiple()->options(['draft' => 'Draft', 'unpaid' => 'Unpaid', 'partially_paid' => 'Partially paid', 'paid' => 'Paid']),
            DateRangeFilter::make('payroll_month', 'Payroll month'),
            DateRangeFilter::make('payment_date', 'Last payment'),
            NumberRangeFilter::make('total_salary_expense', 'Salary expense', '৳'),
            NumberRangeFilter::make('due_amount', 'Outstanding', '৳'),
            TernaryFilter::make('has_due')
                ->label('Has outstanding salary')
                ->queries(
                    true: fn (Builder $query): Builder => $query->where('due_amount', '>', 0),
                    false: fn (Builder $query): Builder => $query->where('due_amount', '<=', 0),
                ),
            TernaryFilter::make('has_payments')
                ->label('Has recorded payments')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereHas('payments'),
                    false: fn (Builder $query): Builder => $query->whereDoesntHave('payments'),
                ),
        ])->recordActions([
            ActionGroup::make([
                ViewAction::make(),
                EditAction::make()->visible(fn (Payroll $record): bool => $record->paid_amount === '0.00'),
                Action::make('payment')->label('Record payment')->icon(Heroicon::Banknotes)->color('success')->visible(fn (Payroll $record): bool => (float) $record->due_amount > 0 && auth()->user()->can('Pay:Payroll'))->schema([
                    TextInput::make('amount')->numeric()->inputMode('decimal')->required()->minValue(0.01)->maxValue(fn (Payroll $record): float => (float) $record->due_amount),
                    DatePicker::make('payment_date')->default(today())->required(),
                    TextInput::make('method'),
                    TextInput::make('reference'),
                    Textarea::make('note'),
                ])->action(function (Payroll $record, array $data): void {
                    abort_unless(auth()->user()->can('Pay:Payroll'), 403);
                    app(RecordPayrollPaymentAction::class)->execute($record, (string) $data['amount'], auth()->user(), $data['payment_date'], $data['method'] ?? null, $data['reference'] ?? null, $data['note'] ?? null);
                    Notification::make()->title('Salary payment recorded')->success()->send();
                }),
            ]),
        ], position: RecordActionsPosition::BeforeColumns)->toolbarActions([
            BulkActionGroup::make([
                GuardedBulkAction::make(
                    name: 'bulkSettle',
                    label: 'Settle outstanding in full',
                    icon: Heroicon::Banknotes,
                    color: 'success',
                    eligible: fn (Payroll $record): bool => (float) $record->due_amount > 0,
                    handle: fn (Payroll $record, User $user, array $data): mixed => app(RecordPayrollPaymentAction::class)->execute(
                        $record,
                        (string) $record->due_amount,
                        $user,
                        (string) $data['payment_date'],
                        $data['method'] ?? null,
                        $data['reference'] ?? null,
                        $data['note'] ?? null,
                    ),
                    ability: 'Pay:Payroll',
                    skipReason: 'nothing outstanding',
                    schema: [
                        DatePicker::make('payment_date')->default(today())->required(),
                        TextInput::make('method'),
                        TextInput::make('reference'),
                        Textarea::make('note'),
                    ],
                    confirmation: 'Each selected payroll is paid its full outstanding balance.',
                ),
                GuardedBulkAction::make(
                    name: 'bulkDelete',
                    label: 'Delete unpaid payrolls',
                    icon: Heroicon::Trash,
                    color: 'danger',
                    eligible: fn (Payroll $record): bool => ! $record->payments()->exists() && (float) $record->paid_amount === 0.0,
                    handle: fn (Payroll $record): mixed => $record->delete(),
                    ability: 'DeleteAny:Payroll',
                    skipReason: 'payments already recorded',
                ),
            ]),
        ]);
    }
}
