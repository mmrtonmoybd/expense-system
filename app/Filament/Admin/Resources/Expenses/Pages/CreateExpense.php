<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Pages;

use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Support\AuditLogger;
use App\Support\TransactionNumberGenerator;
use Filament\Resources\Pages\CreateRecord;

final class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['expense_number'] = app(TransactionNumberGenerator::class)->next('expense', $data['expense_date']);
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(AuditLogger::class)->record(auth()->user(), 'expense_created', $this->record, newValues: $this->record->only(['expense_number', 'expense_date', 'expense_category_id', 'amount', 'description']));
    }
}
