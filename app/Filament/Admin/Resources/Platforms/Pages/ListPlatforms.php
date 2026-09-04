<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Platforms\Pages;

use App\Filament\Admin\Concerns\PassesTableStateToWidgets;
use App\Filament\Admin\Resources\Platforms\PlatformResource;
use App\Filament\Admin\Resources\Platforms\Widgets\PlatformStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPlatforms extends ListRecords
{
    use PassesTableStateToWidgets;

    protected static string $resource = PlatformResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PlatformStatsOverview::class,
        ];
    }
}
