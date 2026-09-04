<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Filament\Admin\Actions\GuardedBulkAction;
use App\Filament\Admin\Filters\DateRangeFilter;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('id')
                        ->grow(false)
                        ->sortable()
                        ->searchable(),
                    Tables\Columns\ImageColumn::make('avatar_url')
                        ->defaultImageUrl(url('https://www.gravatar.com/avatar/64e1b8d34f425d19e1ee2ea7236d3028?d=mp&r=g&s=250'))
                        ->label('Avatar')
                        ->alignEnd()
                        ->square()
                        ->grow(false),
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('name')
                            ->copyable()
                            ->sortable()
                            ->searchable(),
                        Tables\Columns\TextColumn::make('email')
                            ->copyable()
                            ->sortable()
                            ->icon(Heroicon::Envelope)
                            ->searchable(),
                    ])
                        ->alignStart(),
                    Tables\Columns\TextColumn::make('roles.name')
                        ->badge()
                        ->weight(FontWeight::Bold)
                        ->formatStateUsing(fn (string $state): string => Str::title(str_replace('_', ' ', $state)))
                        ->sortable()
                        ->searchable(),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('updated_at')
                            ->since()
                            ->icon(Heroicon::Calendar)
                            ->sortable()
                            ->searchable(),
                        Tables\Columns\TextColumn::make('created_at')
                            ->date()
                            ->icon(Heroicon::Calendar)
                            ->sortable()
                            ->searchable(),
                    ])
                        ->alignEnd()
                        ->grow(false),
                ]),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('email_verified_at'),
                    ),
                TernaryFilter::make('has_roles')
                    ->label('Has any role')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('roles'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('roles'),
                    ),
                TernaryFilter::make('avatar_url')
                    ->label('Has an avatar')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('avatar_url'),
                        false: fn (Builder $query): Builder => $query->whereNull('avatar_url'),
                    ),
                DateRangeFilter::make('created_at', 'Registered', timestamp: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()->visible(fn (User $record): bool => $record->getKey() !== auth()->id()),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    GuardedBulkAction::make(
                        name: 'bulkDelete',
                        label: 'Delete',
                        icon: Heroicon::Trash,
                        color: 'danger',
                        eligible: fn (User $record): bool => $record->getKey() !== auth()->id(),
                        handle: fn (User $record): mixed => $record->delete(),
                        ability: 'DeleteAny:User',
                        skipReason: 'you cannot delete your own account',
                    ),
                ]),
            ]);
    }
}
