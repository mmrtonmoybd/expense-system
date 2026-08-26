<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Schemas;

use App\Models\Sale;
use App\Support\CostingMethod;
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
                TextEntry::make('total_usd')->label('USD sold')->numeric(decimalPlaces: 2), TextEntry::make('sell_rate')->label('Sell rate')->money('BDT')->placeholder('-'), TextEntry::make('total_bdt_revenue')->label('Total sale amount')->money('BDT'),
                TextEntry::make('note')->columnSpanFull()->placeholder('-'),
            ])])->columnSpanFull(),
            Section::make('Costing snapshot')
                ->description('Calculated with the costing method that was active when this sale was posted. It never changes when the system-wide method changes.')
                ->visible(fn (Sale $record): bool => $canViewCosts && $record->costing_method !== null)
                ->schema([Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextEntry::make('costing_method')
                        ->label('Costing method')
                        ->badge()
                        ->formatStateUsing(fn (CostingMethod $state): string => $state->label()),
                    TextEntry::make('cost_rate')->label('Cost rate')->money('BDT')->placeholder('-'),
                    TextEntry::make('cost_amount')->label('Cost amount')->money('BDT')->placeholder('-'),
                    ...($canViewProfit ? [TextEntry::make('profit_amount')->label('Profit')->money('BDT')->placeholder('-')] : []),
                    TextEntry::make('costing_calculated_at')->label('Calculated at')->dateTime()->placeholder('-'),
                ])])
                ->columnSpanFull(),
            Section::make('Cost breakdown')
                ->description('Inventory layers this sale consumed automatically.')
                ->collapsible()
                ->collapsed()
                ->visible(fn (Sale $record): bool => $canViewCosts
                    && $record->costing_method?->usesLayers() === true
                    && $record->costAllocations()->exists())
                ->schema([RepeatableEntry::make('costAllocations')->hiddenLabel()->schema([
                    TextEntry::make('purchase.purchase_number')->label('Purchase')->placeholder('Opening / adjustment layer'),
                    TextEntry::make('quantity_used')->label('USD used')->numeric(decimalPlaces: 2),
                    TextEntry::make('purchase_rate')->label('Layer rate')->money('BDT'),
                    TextEntry::make('cost_amount')->label('Cost')->money('BDT'),
                ])->columns(['default' => 1, 'md' => 4])])
                ->columnSpanFull(),
            Section::make('Platform lines')->schema([RepeatableEntry::make('lines')->schema([
                TextEntry::make('platform.name')->label('Platform'), TextEntry::make('usd_quantity')->label('USD')->numeric(decimalPlaces: 2), TextEntry::make('selling_rate')->label('Selling rate')->money('BDT'), TextEntry::make('revenue')->money('BDT'),
                ...($canViewCosts ? [TextEntry::make('weighted_cost_rate')->label('Cost rate')->money('BDT'), TextEntry::make('inventory_cost')->money('BDT')] : []),
                ...($canViewProfit ? [TextEntry::make('gross_profit')->money('BDT')] : []),
            ])->columns(['default' => 1, 'md' => 4])])->columnSpanFull(),
        ]);
    }
}
