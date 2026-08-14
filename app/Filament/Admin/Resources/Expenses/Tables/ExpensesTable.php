<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('expense_date', 'desc')->columns([
            TextColumn::make('expense_number')->searchable()->copyable(), TextColumn::make('expense_date')->date()->sortable(), TextColumn::make('category.name')->label('Category')->sortable(), TextColumn::make('amount')->money('BDT')->sortable(), TextColumn::make('description')->limit(45)->searchable(), TextColumn::make('vendor')->searchable()->toggleable(), TextColumn::make('creator.name')->label('Created by')->toggleable(),
        ])->filters([SelectFilter::make('expense_category_id')->relationship('category', 'name')->searchable()->preload(), SelectFilter::make('created_by')->relationship('creator', 'name')->searchable()->preload()])->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
