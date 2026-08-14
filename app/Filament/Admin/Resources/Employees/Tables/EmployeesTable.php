<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('name')->columns([
            TextColumn::make('employee_code')->searchable(), TextColumn::make('name')->searchable()->description(fn ($record): string => $record->designation), TextColumn::make('phone')->searchable(), TextColumn::make('current_salary')->money('BDT')->visible(fn (): bool => auth()->user()->can('ViewPayroll')), TextColumn::make('joining_date')->date()->sortable(), IconColumn::make('is_active')->boolean(),
        ])->filters([TernaryFilter::make('is_active')->label('Active')])->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
