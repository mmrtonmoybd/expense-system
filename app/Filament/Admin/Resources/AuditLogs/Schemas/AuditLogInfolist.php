<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('action'),
                TextEntry::make('auditable_type'),
                TextEntry::make('auditable_id')
                    ->numeric(),
                TextEntry::make('old_values')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('new_values')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('ip_address')
                    ->placeholder('-'),
                TextEntry::make('occurred_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
