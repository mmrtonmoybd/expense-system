<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UsdPurchases\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

final class UsdPurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('USD purchase')->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    DatePicker::make('purchase_date')->default(today())->required()->native(false),
                    TextInput::make('usd_quantity')->label('USD quantity')->numeric()->inputMode('decimal')->minValue(0.00000001)->required()->live(onBlur: true),
                    TextInput::make('purchase_rate')->label('Purchase rate (BDT/USD)')->numeric()->inputMode('decimal')->prefix('৳')->minValue(0.00000001)->required()->live(onBlur: true),
                    Placeholder::make('bdt_preview')->label('BDT total preview')->content(fn (Get $get): string => Number::currency((float) ($get('usd_quantity') ?: 0) * (float) ($get('purchase_rate') ?: 0), 'BDT')),
                    TextInput::make('source')->label('Source / vendor')->maxLength(255),
                    TextInput::make('reference')->maxLength(255),
                    FileUpload::make('attachment_path')->label('Attachment')->directory('purchases')->maxSize(5120)->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
                    Textarea::make('note')->rows(3),
                ]),
            ])->columnSpanFull(),
        ]);
    }
}
