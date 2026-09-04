<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Platforms;

use App\Filament\Admin\Resources\Platforms\Pages\CreatePlatform;
use App\Filament\Admin\Resources\Platforms\Pages\EditPlatform;
use App\Filament\Admin\Resources\Platforms\Pages\ListPlatforms;
use App\Filament\Admin\Resources\Platforms\Pages\ViewPlatform;
use App\Filament\Admin\Resources\Platforms\Schemas\PlatformForm;
use App\Filament\Admin\Resources\Platforms\Schemas\PlatformInfolist;
use App\Filament\Admin\Resources\Platforms\Tables\PlatformsTable;
use App\Filament\Admin\Resources\Platforms\Widgets\PlatformPerformanceStats;
use App\Filament\Admin\Resources\Platforms\Widgets\PlatformStatsOverview;
use App\Models\Platform;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class PlatformResource extends Resource
{
    protected static ?string $model = Platform::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::GlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('panel.resources.platform.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.platform.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Customers');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'short_name', 'description'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['Short name' => $record->short_name, 'Status' => $record->is_active ? 'Active' : 'Inactive']);
    }

    public static function form(Schema $schema): Schema
    {
        return PlatformForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlatformInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [
            PlatformPerformanceStats::class,
            PlatformStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatforms::route('/'),
            'create' => CreatePlatform::route('/create'),
            'view' => ViewPlatform::route('/{record}'),
            'edit' => EditPlatform::route('/{record}/edit'),
        ];
    }
}
