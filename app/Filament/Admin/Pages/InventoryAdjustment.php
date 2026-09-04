<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\AdjustInventoryAction;
use App\Actions\SetOpeningInventoryAction;
use App\Models\InventoryState;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class InventoryAdjustment extends Page
{
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.admin.pages.inventory-adjustment';

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.inventory_adjustment');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Inventory');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:InventoryAdjustment') ?? false;
    }

    public function getTitle(): string
    {
        return __('panel.pages.inventory_adjustment');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([Section::make('Controlled inventory adjustment')->description('Creates a permanent ledger entry. Opening balance is available only before the first inventory transaction.')->schema([Grid::make(['default' => 1, 'md' => 2])->schema([Placeholder::make('available')->content(fn (): string => (string) (InventoryState::query()->value('usd_balance') ?? '0').' USD'), Select::make('type')->options(['opening_balance' => 'Opening balance', 'positive_adjustment' => 'Positive adjustment', 'negative_adjustment' => 'Negative adjustment'])->required()->live(), TextInput::make('usd_quantity')->numeric()->inputMode('decimal')->minValue(0.00000001)->required(), TextInput::make('inventory_value_bdt')->label('Opening inventory value (BDT)')->numeric()->inputMode('decimal')->minValue(0.01)->required(fn (Get $get): bool => $get('type') === 'opening_balance')->visible(fn (Get $get): bool => $get('type') === 'opening_balance'), Textarea::make('reason')->required()])])->columnSpanFull()]);
    }

    public function adjust(): void
    {
        abort_unless(auth()->user()?->can('Adjust:Inventory'), 403);
        $data = $this->form->getState();
        if ($data['type'] === 'opening_balance') {
            app(SetOpeningInventoryAction::class)->execute((string) $data['usd_quantity'], (string) $data['inventory_value_bdt'], auth()->user(), $data['reason']);
        } else {
            app(AdjustInventoryAction::class)->execute($data['type'], (string) $data['usd_quantity'], auth()->user(), $data['reason']);
        }
        $this->form->fill();
        Notification::make()->title('Inventory adjusted')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('adjust')->submit('adjust')->color('warning')->requiresConfirmation()->visible(fn (): bool => auth()->user()?->can('Adjust:Inventory') ?? false)];
    }
}
