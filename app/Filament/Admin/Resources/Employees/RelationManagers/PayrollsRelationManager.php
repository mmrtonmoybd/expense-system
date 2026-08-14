<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payroll_number')
            ->columns([
                TextColumn::make('payroll_number')
                    ->searchable(),
                TextColumn::make('payroll_month')
                    ->date()
                    ->sortable(),
                TextColumn::make('base_salary')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('allowance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bonus')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deduction')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('adjustment')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_salary_expense')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('due_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('payroll_month', 'desc');
    }
}
