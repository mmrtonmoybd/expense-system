<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer details')->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('phone')->tel()->required()->maxLength(30),
                    TextInput::make('email')->email()->nullable()->maxLength(255),
                    TextInput::make('company_name')->maxLength(255),
                    Textarea::make('address')->rows(3),
                    Textarea::make('notes')->rows(3),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
            ])->columnSpanFull(),
        ]);
    }
}
