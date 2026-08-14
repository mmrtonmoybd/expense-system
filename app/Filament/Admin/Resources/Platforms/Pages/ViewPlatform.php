<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Platforms\Pages;

use App\Filament\Admin\Resources\Platforms\PlatformResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewPlatform extends ViewRecord
{
    protected static string $resource = PlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
