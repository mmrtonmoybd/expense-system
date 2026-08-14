<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $canViewProfit = auth()->user()?->can('ViewProfit') ?? false;

        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('phone'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('company_name')
                    ->placeholder('-'),
                TextEntry::make('address')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('posted_usd_total')->label('Total USD sold')->state(fn (Customer $record): string => bcadd((string) $record->sales()->where('status', 'posted')->sum('total_usd'), '0', 8))->numeric(decimalPlaces: 2),
                TextEntry::make('posted_revenue_total')->label('Total revenue')->state(fn (Customer $record): string => bcadd((string) $record->sales()->where('status', 'posted')->sum('total_bdt_revenue'), '0', 2))->money('BDT'),
                ...($canViewProfit ? [TextEntry::make('posted_profit_total')->label('Total gross profit')->state(fn (Customer $record): string => bcadd((string) $record->sales()->where('status', 'posted')->sum('total_gross_profit'), '0', 2))->money('BDT')] : []),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
