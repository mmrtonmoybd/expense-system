<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Schemas;

use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

final class PayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([Section::make('Monthly payroll')->schema([Grid::make(['default' => 1, 'md' => 2])->schema([
            Select::make('employee_id')->relationship('employee', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->searchable()->preload()->required()->live()->disabledOn('edit')->afterStateUpdated(fn (Set $set, $state) => $set('base_salary', Employee::query()->whereKey($state)->value('current_salary'))), DatePicker::make('payroll_month')->label('Payroll month')->displayFormat('F Y')->default(today()->startOfMonth())->required()->native(false)->disabledOn('edit'),
            TextInput::make('base_salary')->numeric()->inputMode('decimal')->prefix('৳')->required()->disabled()->dehydrated(), TextInput::make('allowance')->numeric()->inputMode('decimal')->prefix('৳')->minValue(0)->default(0)->live(onBlur: true), TextInput::make('bonus')->numeric()->inputMode('decimal')->prefix('৳')->minValue(0)->default(0)->live(onBlur: true), TextInput::make('deduction')->numeric()->inputMode('decimal')->prefix('৳')->minValue(0)->default(0)->live(onBlur: true), TextInput::make('adjustment')->numeric()->inputMode('decimal')->prefix('৳')->default(0)->live(onBlur: true),
            Placeholder::make('total_preview')->label('Salary expense')->content(fn (Get $get): string => Number::currency((float) ($get('base_salary') ?: 0) + (float) ($get('allowance') ?: 0) + (float) ($get('bonus') ?: 0) - (float) ($get('deduction') ?: 0) + (float) ($get('adjustment') ?: 0), 'BDT')), Textarea::make('notes'),
        ])])->columnSpanFull()]);
    }
}
