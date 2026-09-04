<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Schemas;

use App\Models\Customer;
use App\Models\CustomerPlatform;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;

final class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sale')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            Select::make('customer_id')
                                ->label('Customer')
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    $lines = collect($get('lines') ?? [])
                                        ->map(fn (array $line): array => [
                                            ...$line,
                                            'platform_id' => null,
                                            'selling_rate' => null,
                                        ])
                                        ->all();

                                    $set('lines', $lines === [] ? [[]] : $lines);
                                })
                                ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                                    ->where('is_active', true)
                                    ->where(function (Builder $query) use ($search): void {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('phone', 'like', "%{$search}%")
                                            ->orWhere('company_name', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Customer $customer): array => [
                                        $customer->id => "{$customer->name} ({$customer->phone})",
                                    ])
                                    ->all())
                                ->getOptionLabelUsing(fn ($value): ?string => ($customer = Customer::query()->find($value))
                                    ? "{$customer->name} ({$customer->phone})"
                                    : null),
                            DatePicker::make('sale_date')
                                ->default(today())
                                ->required()
                                ->native(false),
                            Textarea::make('note')
                                ->rows(2),
                        ]),
                ])
                ->columnSpanFull(),
            Section::make('Platform lines')
                ->schema([
                    Repeater::make('lines')
                        ->relationship()
                        ->hiddenLabel()
                        ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => Arr::except($data, [
                            'weighted_cost_rate',
                            'revenue',
                            'inventory_cost',
                            'gross_profit',
                            'is_rate_overridden',
                            'rate_overridden_by',
                        ]))
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                            ...$data,
                            'weighted_cost_rate' => '0',
                            'revenue' => '0',
                            'inventory_cost' => '0',
                            'gross_profit' => '0',
                            'is_rate_overridden' => false,
                            'rate_overridden_by' => null,
                        ])
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Add platform line')
                        ->reorderable(false)
                        ->table([
                            TableColumn::make('Platform')->markAsRequired()->width('16rem'),
                            TableColumn::make('USD')->markAsRequired()->width('7rem'),
                            TableColumn::make('Rate')->markAsRequired()->width('9rem'),
                            TableColumn::make('Details')->width('16rem'),
                        ])
                        ->compact()
                        ->schema([
                            Select::make('platform_id')
                                ->label('Platform')
                                ->options(fn (Get $get): array => CustomerPlatform::query()
                                    ->where('customer_id', $get('../../customer_id'))
                                    ->with('platform')
                                    ->get()
                                    ->filter(fn (CustomerPlatform $assignment): bool => $assignment->platform?->is_active ?? false)
                                    ->mapWithKeys(fn (CustomerPlatform $assignment): array => [
                                        $assignment->platform_id => $assignment->platform->name,
                                    ])
                                    ->all())
                                ->searchable()
                                ->required()
                                ->live()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->afterStateUpdated(fn (Get $get, Set $set, int|string|null $state): mixed => $set(
                                    'selling_rate',
                                    self::customerPlatformRate($get('../../customer_id'), $state),
                                )),
                            TextInput::make('usd_quantity')
                                ->label('USD')
                                ->numeric()
                                ->inputMode('decimal')
                                ->minValue(0.00000001)
                                ->required()
                                ->live(onBlur: true),
                            TextInput::make('selling_rate')
                                ->label('Rate')
                                ->numeric()
                                ->inputMode('decimal')
                                ->prefix('৳')
                                ->required()
                                ->live(onBlur: true)
                                ->disabled(fn (): bool => ! self::canOverrideRate())
                                ->dehydrated()
                                // The field is submitted even while disabled, so the stored
                                // rate is resolved on the server. A tampered payload from a
                                // user without the override permission is discarded here
                                // instead of only being caught later at submit time.
                                ->dehydrateStateUsing(fn (Get $get, mixed $state): mixed => self::canOverrideRate()
                                    ? $state
                                    : self::customerPlatformRate($get('../../customer_id'), $get('platform_id')) ?? $state)
                                ->afterStateHydrated(function (Get $get, Set $set, mixed $state): void {
                                    if (filled($state)) {
                                        return;
                                    }

                                    $set('selling_rate', self::customerPlatformRate(
                                        $get('../../customer_id'),
                                        $get('platform_id'),
                                    ));
                                }),
                            Grid::make(1)
                                ->schema([
                                    TextInput::make('rate_override_reason')
                                        ->label('Override reason')
                                        ->visible(fn (string $operation): bool => $operation === 'edit'
                                            && self::canOverrideRate()),
                                    TextInput::make('note')
                                        ->label('Note')
                                        ->placeholder('Optional note'),
                                ]),
                        ]),
                ])
                ->columnSpanFull(),
            Section::make('Sale totals')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            Placeholder::make('total_usd_preview')
                                ->label('Total USD')
                                ->content(fn (Get $get): string => number_format(
                                    collect($get('lines') ?? [])->sum(
                                        fn (array $line): float => (float) ($line['usd_quantity'] ?? 0),
                                    ),
                                    2,
                                )),
                            Placeholder::make('total_revenue_preview')
                                ->label('Sale total')
                                ->content(fn (Get $get): string => Number::currency(
                                    collect($get('lines') ?? [])->sum(
                                        fn (array $line): float => (float) ($line['usd_quantity'] ?? 0)
                                            * (float) ($line['selling_rate'] ?? 0),
                                    ),
                                    'BDT',
                                )),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    private static function canOverrideRate(): bool
    {
        return auth()->user()?->can('OverrideRate:Sale') ?? false;
    }

    private static function customerPlatformRate(mixed $customerId, mixed $platformId): ?string
    {
        if (blank($customerId) || blank($platformId)) {
            return null;
        }

        return CustomerPlatform::query()
            ->where('customer_id', $customerId)
            ->where('platform_id', $platformId)
            ->value('current_selling_rate');
    }
}
