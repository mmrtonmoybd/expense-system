<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Pages;

use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Support\AuditLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $oldValues = $record->only(['expense_date', 'expense_category_id', 'amount', 'description', 'vendor', 'reference', 'note']);
        $record->update($data);
        app(AuditLogger::class)->record(auth()->user(), 'expense_updated', $record, $oldValues, $record->only(array_keys($oldValues)));

        return $record;
    }
}
