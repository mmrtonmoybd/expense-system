<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class UsdPurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('purchase_number'),
                TextEntry::make('purchase_date')
                    ->date(),
                TextEntry::make('usd_quantity')
                    ->numeric(),
                TextEntry::make('purchase_rate')
                    ->numeric(),
                TextEntry::make('bdt_total')
                    ->numeric(),
                TextEntry::make('source')
                    ->placeholder('-'),
                TextEntry::make('reference')
                    ->placeholder('-'),
                TextEntry::make('attachment_path')
                    ->placeholder('-'),
                TextEntry::make('note')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('posted_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('posted_at')
                    ->dateTime()
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
