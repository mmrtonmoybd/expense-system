<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Pages;

use App\Actions\RecalculatePendingSaleAction;
use App\Filament\Admin\Resources\Sales\SaleResource;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->status !== 'pending') {
            return;
        }

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $this->record = app(RecalculatePendingSaleAction::class)->execute($this->record, $user);
    }
}
