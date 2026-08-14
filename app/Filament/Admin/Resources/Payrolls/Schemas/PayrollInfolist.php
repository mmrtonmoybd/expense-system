<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class PayrollInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('payroll_number'),
                TextEntry::make('employee_id')
                    ->numeric(),
                TextEntry::make('payroll_month')
                    ->date(),
                TextEntry::make('base_salary')
                    ->numeric(),
                TextEntry::make('allowance')
                    ->numeric(),
                TextEntry::make('bonus')
                    ->numeric(),
                TextEntry::make('deduction')
                    ->numeric(),
                TextEntry::make('adjustment')
                    ->numeric(),
                TextEntry::make('total_salary_expense')
                    ->numeric(),
                TextEntry::make('paid_amount')
                    ->numeric(),
                TextEntry::make('due_amount')
                    ->numeric(),
                TextEntry::make('payment_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
