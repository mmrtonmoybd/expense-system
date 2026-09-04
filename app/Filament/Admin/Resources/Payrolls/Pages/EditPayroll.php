<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Pages;

use App\Filament\Admin\Resources\Payrolls\PayrollResource;
use App\Support\Money;
use App\Support\PayrollCalculator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

final class EditPayroll extends EditRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->visible(fn (): bool => ! $this->record->payments()->exists()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['base_salary'] = $this->record->base_salary;
        $data['total_salary_expense'] = app(PayrollCalculator::class)->total($data);

        if (Money::compareBdt($data['total_salary_expense'], (string) $this->record->paid_amount) === -1) {
            throw ValidationException::withMessages(['data.deduction' => 'Salary expense cannot be lower than the amount already paid.']);
        }

        $paid = (string) $this->record->paid_amount;
        $data['due_amount'] = Money::subBdt($data['total_salary_expense'], $paid);
        $data['status'] = match (true) {
            Money::isZero($data['due_amount'], Money::BdtScale) => 'paid',
            Money::isZero($paid, Money::BdtScale) => 'unpaid',
            default => 'partially_paid',
        };

        return $data;
    }
}
