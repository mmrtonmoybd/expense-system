<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([Section::make('Expense')->schema([Grid::make(['default' => 1, 'md' => 2])->schema([
            DatePicker::make('expense_date')->default(today())->required()->native(false), Select::make('expense_category_id')->relationship('category', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('sort_order'))->searchable()->preload()->required(),
            TextInput::make('amount')->numeric()->inputMode('decimal')->prefix('৳')->minValue(0.01)->required(), TextInput::make('vendor')->maxLength(255), TextInput::make('reference')->maxLength(255),
            FileUpload::make('attachment_path')->directory('expenses')->maxSize(5120)->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']), Textarea::make('description')->required()->rows(3), Textarea::make('note')->rows(3),
        ])])->columnSpanFull()]);
    }
}
