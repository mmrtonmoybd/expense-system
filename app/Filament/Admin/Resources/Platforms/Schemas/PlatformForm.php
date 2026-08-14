<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Platforms\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PlatformForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([Section::make('Platform')->schema([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('short_name')->maxLength(50),
                FileUpload::make('logo_path')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)->directory('platforms')->visibility('public'),
                TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                Textarea::make('description'),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ])->columnSpanFull()]);
    }
}
