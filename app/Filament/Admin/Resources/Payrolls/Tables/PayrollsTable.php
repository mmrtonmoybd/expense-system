<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Tables;

use App\Actions\RecordPayrollPaymentAction;
use App\Models\Payroll;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class PayrollsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('payroll_month', 'desc')->columns([
            TextColumn::make('payroll_number')->searchable(), TextColumn::make('employee.name')->searchable(), TextColumn::make('payroll_month')->date('F Y')->sortable(), TextColumn::make('total_salary_expense')->label('Expense')->money('BDT'), TextColumn::make('paid_amount')->money('BDT'), TextColumn::make('due_amount')->money('BDT')->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'), TextColumn::make('status')->badge(),
        ])->filters([SelectFilter::make('employee_id')->relationship('employee', 'name')->searchable()->preload(), SelectFilter::make('status')->options(['unpaid' => 'Unpaid', 'partially_paid' => 'Partially paid', 'paid' => 'Paid'])])->recordActions([ViewAction::make(), EditAction::make()->visible(fn (Payroll $record): bool => $record->paid_amount === '0.00'), Action::make('payment')->label('Record payment')->color('success')->visible(fn (Payroll $record): bool => (float) $record->due_amount > 0 && auth()->user()->can('Pay:Payroll'))->schema([TextInput::make('amount')->numeric()->inputMode('decimal')->required()->minValue(0.01), DatePicker::make('payment_date')->default(today())->required(), TextInput::make('method'), TextInput::make('reference'), Textarea::make('note')])->action(function (Payroll $record, array $data): void {
            abort_unless(auth()->user()->can('Pay:Payroll'), 403);
            app(RecordPayrollPaymentAction::class)->execute($record, (string) $data['amount'], auth()->user(), $data['payment_date'], $data['method'] ?? null, $data['reference'] ?? null, $data['note'] ?? null);
            Notification::make()->title('Salary payment recorded')->success()->send();
        })]);
    }
}
