<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([Section::make('Employee')->schema([Grid::make(['default' => 1, 'md' => 2])->schema([
            TextInput::make('employee_code')->required()->maxLength(50)->unique(ignoreRecord: true), TextInput::make('name')->required(), TextInput::make('phone')->tel()->required(), TextInput::make('email')->email()->nullable(), TextInput::make('designation')->required(), DatePicker::make('joining_date')->required()->native(false), TextInput::make('current_salary')->numeric()->inputMode('decimal')->prefix('৳')->minValue(0)->required(), Select::make('user_id')->relationship('user', 'name')->searchable()->preload(), Toggle::make('is_active')->default(true), Textarea::make('salary_change_reason')->label('Salary change reason')->visibleOn('edit'), Textarea::make('notes'),
        ])])->columnSpanFull()]);
    }
}
