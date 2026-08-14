<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Customers\RelationManagers;

use App\Actions\AssignCustomerPlatformAction;
use App\Actions\UpdateCustomerPlatformRateAction;
use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\Platform;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class CustomerPlatformsRelationManager extends RelationManager
{
    protected static string $relationship = 'customerPlatforms';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform_id')
                ->label('Platform')
                ->options(function (?CustomerPlatform $record): array {
                    $query = Platform::query()->orderBy('sort_order')->orderBy('name');

                    if ($record === null) {
                        $query->where('is_active', true)
                            ->whereNotIn(
                                'id',
                                $this->customer()->customerPlatforms()->select('platform_id'),
                            );
                    } else {
                        $query->whereKey($record->platform_id);
                    }

                    return $query->pluck('name', 'id')->all();
                })
                ->searchable()
                ->required()
                ->disabledOn('edit'),
            TextInput::make('current_selling_rate')
                ->label('Selling rate (BDT/USD)')
                ->numeric()
                ->inputMode('decimal')
                ->minValue(0.00000001)
                ->prefix('৳')
                ->required(),
            Textarea::make('note')
                ->label('Reason / note')
                ->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('platform.name')->label('Platform')->searchable(),
                TextColumn::make('current_selling_rate')->label('Current rate')->money('BDT')->sortable(),
                TextColumn::make('note')->label('Reason / note')->placeholder('-')->wrap(),
                TextColumn::make('updated_at')->label('Effective')->dateTime()->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Attach platform')
                    ->visible(fn (): bool => Gate::forUser($this->user())->allows('update', $this->customer()))
                    ->using(fn (array $data): CustomerPlatform => app(AssignCustomerPlatformAction::class)->execute(
                        $this->customer(),
                        (int) $data['platform_id'],
                        (string) $data['current_selling_rate'],
                        $this->user(),
                        $data['note'] ?? null,
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => Gate::forUser($this->user())->allows('update', $this->customer()))
                    ->using(fn (CustomerPlatform $record, array $data): CustomerPlatform => app(UpdateCustomerPlatformRateAction::class)->execute(
                        $record,
                        (string) $data['current_selling_rate'],
                        $this->user(),
                        $data['note'] ?? null,
                    )),
            ]);
    }

    private function customer(): Customer
    {
        $customer = $this->getOwnerRecord();

        abort_unless($customer instanceof Customer, 404);

        return $customer;
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
