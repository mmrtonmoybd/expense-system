<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryTransactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class InventoryTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('transaction_type'),
                TextEntry::make('source_type'),
                TextEntry::make('source_id')
                    ->numeric(),
                TextEntry::make('usd_in')
                    ->numeric(),
                TextEntry::make('usd_out')
                    ->numeric(),
                TextEntry::make('cost_rate')
                    ->numeric(),
                TextEntry::make('bdt_value_in')
                    ->numeric(),
                TextEntry::make('bdt_value_out')
                    ->numeric(),
                TextEntry::make('resulting_usd_balance')
                    ->numeric(),
                TextEntry::make('resulting_inventory_value_bdt')
                    ->numeric(),
                TextEntry::make('resulting_weighted_average_cost')
                    ->money(),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('occurred_at')
                    ->dateTime(),
                TextEntry::make('note')
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
