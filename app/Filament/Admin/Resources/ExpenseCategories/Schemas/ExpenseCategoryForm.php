<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseCategories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([Section::make('Category')->schema([Grid::make(['default' => 1, 'md' => 2])->schema([TextInput::make('name')->required()->maxLength(255), TextInput::make('sort_order')->numeric()->minValue(0)->default(0), Textarea::make('description'), Toggle::make('is_active')->default(true)])])->columnSpanFull()]);
    }
}
