<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Platforms\Pages;

use App\Filament\Admin\Resources\Platforms\PlatformResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditPlatform extends EditRecord
{
    protected static string $resource = PlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
