<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $canViewProfit = auth()->user()?->can('ViewProfit') ?? false;
        $canViewCosts = auth()->user()?->can('ViewInternalCosts') ?? false;

        return $schema->components([
            Section::make('Sale')->schema([Grid::make(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('sale_number')->copyable(), TextEntry::make('invoice_number')->placeholder('-'), TextEntry::make('sale_date')->date(),
                TextEntry::make('customer.name')->label('Customer'), TextEntry::make('customer.phone')->label('Phone'), TextEntry::make('status')->badge(),
                TextEntry::make('total_usd')->label('Total USD')->numeric(decimalPlaces: 2), TextEntry::make('total_bdt_revenue')->label('Revenue')->money('BDT'),
                ...($canViewCosts ? [TextEntry::make('total_inventory_cost')->label('Inventory cost')->money('BDT')] : []),
                ...($canViewProfit ? [TextEntry::make('total_gross_profit')->label('Gross profit')->money('BDT')] : []),
                TextEntry::make('note')->columnSpanFull()->placeholder('-'),
            ])])->columnSpanFull(),
            Section::make('Platform lines')->schema([RepeatableEntry::make('lines')->schema([
                TextEntry::make('platform.name')->label('Platform'), TextEntry::make('usd_quantity')->label('USD')->numeric(decimalPlaces: 2), TextEntry::make('selling_rate')->label('Selling rate')->money('BDT'), TextEntry::make('revenue')->money('BDT'),
                ...($canViewCosts ? [TextEntry::make('weighted_cost_rate')->label('Cost rate')->money('BDT'), TextEntry::make('inventory_cost')->money('BDT')] : []),
                ...($canViewProfit ? [TextEntry::make('gross_profit')->money('BDT')] : []),
            ])->columns(['default' => 1, 'md' => 4])])->columnSpanFull(),
        ]);
    }
}
