<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\CustomerPlatform;
use App\Models\InventoryState;
use App\Services\ProfitPredictor as PredictorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class ProfitPredictor extends Page
{
    public ?array $data = [];

    /** @var array<string,string> */
    public array $result = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Calculator;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.admin.pages.profit-predictor';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:ProfitPredictor') ?? false;
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill(['mode' => 'customer', 'usd_quantity' => '100']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([Section::make('Projection')->schema([Grid::make(['default' => 1, 'md' => 2])->schema([
            Select::make('mode')->options(['customer' => 'Customer rate', 'custom' => 'Custom rate', 'target' => 'Target profit', 'sell_all' => 'Sell all inventory'])->required()->live(),
            Select::make('customer_platform_id')->label('Customer / Platform')->options(fn (): array => CustomerPlatform::query()->with(['customer', 'platform'])->get()->mapWithKeys(fn (CustomerPlatform $rate): array => [$rate->id => "{$rate->customer->name} — {$rate->platform->name}"])->all())->searchable()->visible(fn (Get $get): bool => $get('mode') === 'customer')->required(fn (Get $get): bool => $get('mode') === 'customer')->live()->afterStateUpdated(fn (Set $set, $state) => $set('selling_rate', CustomerPlatform::query()->whereKey($state)->value('current_selling_rate'))),
            TextInput::make('usd_quantity')->label('USD quantity')->numeric()->inputMode('decimal')->required()->visible(fn (Get $get): bool => $get('mode') !== 'sell_all'),
            TextInput::make('selling_rate')->label('Selling rate')->numeric()->inputMode('decimal')->prefix('৳')->visible(fn (Get $get): bool => in_array($get('mode'), ['customer', 'custom', 'sell_all'], true))->disabled(fn (Get $get): bool => $get('mode') === 'customer')->dehydrated(),
            TextInput::make('desired_profit')->label('Desired gross profit')->numeric()->inputMode('decimal')->prefix('৳')->visible(fn (Get $get): bool => $get('mode') === 'target'),
        ])])->columnSpanFull()]);
    }

    public function calculate(PredictorService $predictor): void
    {
        abort_unless(self::canAccess(), 403);
        $data = $this->form->getState();
        $quantity = (string) ($data['usd_quantity'] ?? InventoryState::query()->value('usd_balance') ?? '0');
        if ($data['mode'] === 'sell_all') {
            $quantity = (string) (InventoryState::query()->value('usd_balance') ?? '0');
        }
        if ($data['mode'] === 'target') {
            $rate = $predictor->requiredRate($quantity, (string) $data['desired_profit']);
            $this->result = $predictor->calculate($quantity, $rate);
            $this->result['required_rate'] = $rate;

            return;
        }
        $this->result = $predictor->calculate($quantity, (string) $data['selling_rate']);
    }

    protected function getFormActions(): array
    {
        return [Action::make('calculate')->submit('calculate')->icon('heroicon-o-calculator')];
    }
}
