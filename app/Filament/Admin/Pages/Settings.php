<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\ChangeCostingMethodAction;
use App\Actions\UpdateUsdSellRateAction;
use App\Models\CostingMethodChange;
use App\Models\InventoryState;
use App\Services\UsdCostingService;
use App\Services\UsdInventoryLayerService;
use App\Services\UsdSellRateService;
use App\Support\AuditLogger;
use App\Support\CostingMethod;
use App\Support\Localization;
use App\Support\Money;
use App\Support\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use LogicException;
use UnitEnum;

final class Settings extends Page
{
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.admin.pages.settings';

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Administration');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:Settings') ?? false;
    }

    public function getTitle(): string
    {
        return __('panel.pages.settings');
    }

    public function mount(SettingsRepository $settings): void
    {
        abort_unless(self::canAccess(), 403);
        $keys = [Localization::SettingKey, 'business_name', 'business_logo', 'business_address', 'business_phone', 'business_email', 'business_website', 'invoice_prefix', 'invoice_footer', 'invoice_terms', 'authorized_person', 'signature_label', 'sale_approval_required', 'timezone', 'date_format', 'usd_precision', 'bdt_precision', 'pwa_app_name', 'pwa_short_name', 'pwa_app_icon', 'pwa_favicon', 'pwa_theme_color', 'pwa_background_color', 'login_logo'];
        $this->form->fill([
            ...collect($keys)->mapWithKeys(fn (string $key): array => [$key => $settings->get($key, $this->default($key))])->all(),
            CostingMethod::SettingKey => app(UsdCostingService::class)->activeMethod()->value,
            UsdSellRateService::SettingKey => app(UsdSellRateService::class)->configuredRate(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([Tabs::make('Settings')->tabs([
            Tab::make('Business')->schema([Section::make()->schema([Grid::make(['default' => 1, 'md' => 2])->schema([TextInput::make('business_name')->required(), FileUpload::make('business_logo')->image()->directory('branding')->visibility('public'), Textarea::make('business_address'), TextInput::make('business_phone'), TextInput::make('business_email')->email(), TextInput::make('business_website')->url()])])]),
            Tab::make('Invoice')->schema([Section::make()->schema([Grid::make(['default' => 1, 'md' => 2])->schema([TextInput::make('invoice_prefix')->default('INV'), TextInput::make('authorized_person'), TextInput::make('signature_label'), Textarea::make('invoice_footer'), Textarea::make('invoice_terms')])])]),
            Tab::make('System')->schema([Section::make()->schema([Grid::make(['default' => 1, 'md' => 2])->schema([Toggle::make('sale_approval_required'), Select::make(Localization::SettingKey)->label(__('panel.language.label'))->options(Localization::supported())->default(Localization::Fallback)->required()->native(false)->helperText('Applies to the panel and to printed invoices and reports.'), Select::make('timezone')->options(['Asia/Dhaka' => 'Asia/Dhaka', 'UTC' => 'UTC'])->required(), Select::make('date_format')->options([(string) config('app.date_format') => now()->format((string) config('app.date_format'))])->default(config('app.date_format'))->required(), Select::make('usd_precision')->options([2 => '2', 4 => '4', 8 => '8']), Select::make('bdt_precision')->options([0 => '0', 2 => '2'])])])]),
            $this->costingTab(),
            Tab::make('PWA')->schema([Section::make()->schema([Grid::make(['default' => 1, 'md' => 2])->schema([TextInput::make('pwa_app_name')->required(), TextInput::make('pwa_short_name')->required()->maxLength(12), FileUpload::make('pwa_app_icon')->label('Square app icon')->image()->imageEditor()->imageEditorAspectRatioOptions(['1:1'])->directory('branding')->visibility('public')->minSize(100)->maxSize(4096), FileUpload::make('pwa_favicon')->image()->directory('branding')->visibility('public'), ColorPicker::make('pwa_theme_color'), ColorPicker::make('pwa_background_color'), FileUpload::make('login_logo')->image()->directory('branding')->visibility('public')])])]),
        ])->columnSpanFull()]);
    }

    public function save(SettingsRepository $settings, AuditLogger $auditLogger): void
    {
        abort_unless(auth()->user()?->can('Manage:Settings'), 403);
        $settings->putMany(collect($this->form->getState())->except([
            CostingMethod::SettingKey,
            UsdSellRateService::SettingKey,
        ])->all());
        $auditLogger->record(auth()->user(), 'settings_updated', auth()->user());
        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->submit('save')->keyBindings(['mod+s'])->visible(fn (): bool => auth()->user()?->can('Manage:Settings') ?? false)];
    }

    /**
     * Dollar inventory costing: the method used for new sales plus the operational selling
     * rate. Both are changed through dedicated actions so every change is audited, and both
     * are excluded from the bulk settings save.
     */
    private function costingTab(): Tab
    {
        return Tab::make('Dollar Inventory Costing')
            ->icon(Heroicon::Scale)
            ->schema([
                Section::make('Dollar Inventory Costing')
                    ->description('Applies to new dollar sales only.')
                    ->schema([
                        Callout::make('Historical transactions are never recalculated')
                            ->description('Changing the costing method only affects new dollar sales. Previous transactions, costs and profits will continue using the method that was active when each transaction was created.')
                            ->warning(),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Placeholder::make('current_costing_method')
                                ->label('Current method')
                                ->content(fn (): string => app(UsdCostingService::class)->activeMethod()->label()),
                            Placeholder::make('costing_inventory_position')
                                ->label('Inventory position')
                                ->content(function (): string {
                                    $state = InventoryState::query()->first();
                                    $balance = number_format((float) ($state?->usd_balance ?? 0), 2);
                                    $value = number_format((float) ($state?->inventory_value_bdt ?? 0), 2);
                                    $layers = number_format((float) app(UsdInventoryLayerService::class)->remainingQuantity(), 2);

                                    return "{$balance} USD · Book value ৳{$value} · Layers hold {$layers} USD";
                                }),
                            Radio::make(CostingMethod::SettingKey)
                                ->label('Costing method for new sales')
                                ->options(CostingMethod::options())
                                ->descriptions([
                                    CostingMethod::Average->value => 'Charges every sale the moving weighted average cost of the remaining inventory.',
                                    CostingMethod::Fifo->value => 'Automatically consumes the oldest purchase layers first.',
                                    CostingMethod::Lifo->value => 'Automatically consumes the newest purchase layers first.',
                                ])
                                ->disabled(fn (): bool => ! (auth()->user()?->can('Manage:CostingMethod') ?? false))
                                ->columnSpanFull(),
                            SchemaActions::make([
                                Action::make('changeCostingMethod')
                                    ->label('Change costing method')
                                    ->icon(Heroicon::ArrowPath)
                                    ->color('warning')
                                    ->visible(fn (): bool => auth()->user()?->can('Manage:CostingMethod') ?? false)
                                    ->requiresConfirmation()
                                    ->modalHeading(fn (): string => 'Change dollar costing method?')
                                    ->modalDescription(fn (): string => sprintf(
                                        'Change dollar costing method from %s to %s? This will affect new sales only. Historical transactions and profits will not be recalculated.',
                                        app(UsdCostingService::class)->activeMethod()->shortLabel(),
                                        CostingMethod::fromNullable($this->data[CostingMethod::SettingKey] ?? null)->shortLabel(),
                                    ))
                                    ->schema([Textarea::make('reason')->label('Reason (optional)')->rows(2)])
                                    ->action(fn (array $data): mixed => $this->changeCostingMethod($data['reason'] ?? null)),
                            ])->columnSpanFull(),
                            Placeholder::make('costing_method_history')
                                ->label('Recent method changes')
                                ->content(fn (): string => CostingMethodChange::query()
                                    ->with('changer')
                                    ->latest('changed_at')
                                    ->limit(5)
                                    ->get()
                                    ->map(fn (CostingMethodChange $change): string => sprintf(
                                        '%s → %s on %s by %s',
                                        $change->old_method->shortLabel(),
                                        $change->new_method->shortLabel(),
                                        $change->changed_at->format((string) config('app.datetime_format')),
                                        $change->changer?->name ?? 'system',
                                    ))
                                    ->implode(' · ') ?: 'No method change recorded yet.')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Current dollar sell rate')
                    ->description('The operational rate offered to customers. This is never derived from inventory cost.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Placeholder::make('effective_sell_rate')
                                ->label('Rate shown on the dashboard')
                                ->content(function (): string {
                                    $service = app(UsdSellRateService::class);
                                    $rate = $service->currentRate();

                                    if ($rate === null) {
                                        return 'Not set yet';
                                    }

                                    return '৳'.number_format((float) $rate, 2).($service->isFallback() ? ' (falling back to the last posted sale rate)' : '');
                                }),
                            TextInput::make(UsdSellRateService::SettingKey)
                                ->label('Current dollar sell rate (BDT per USD)')
                                ->numeric()
                                ->inputMode('decimal')
                                ->prefix('৳')
                                ->minValue(0.00000001)
                                ->disabled(fn (): bool => ! (auth()->user()?->can('Manage:SellRate') ?? false)),
                            SchemaActions::make([
                                Action::make('updateSellRate')
                                    ->label('Update sell rate')
                                    ->icon(Heroicon::CurrencyBangladeshi)
                                    ->visible(fn (): bool => auth()->user()?->can('Manage:SellRate') ?? false)
                                    ->schema([Textarea::make('note')->label('Note (optional)')->rows(2)])
                                    ->action(fn (array $data): mixed => $this->updateSellRate($data['note'] ?? null)),
                            ])->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    private function changeCostingMethod(?string $reason): void
    {
        abort_unless(auth()->user()?->can('Manage:CostingMethod'), 403);

        $method = CostingMethod::fromNullable($this->data[CostingMethod::SettingKey] ?? null);

        try {
            app(ChangeCostingMethodAction::class)->execute($method, auth()->user(), $reason);
        } catch (LogicException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        }

        Notification::make()
            ->title("Costing method changed to {$method->shortLabel()}.")
            ->body("New dollar sales will now use {$method->shortLabel()}.")
            ->success()
            ->send();
    }

    private function updateSellRate(?string $note): void
    {
        abort_unless(auth()->user()?->can('Manage:SellRate'), 403);

        $rate = (string) ($this->data[UsdSellRateService::SettingKey] ?? '');

        try {
            app(UpdateUsdSellRateAction::class)->execute($rate, auth()->user(), $note);
        } catch (LogicException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Current dollar sell rate updated to ৳'.number_format((float) Money::rate($rate), 2))->success()->send();
    }

    private function default(string $key): mixed
    {
        return match ($key) {
            Localization::SettingKey => Localization::Fallback,'business_name','pwa_app_name' => config('app.name'),'pwa_short_name' => 'Agency','invoice_prefix' => 'INV','sale_approval_required' => false,'timezone' => 'Asia/Dhaka','date_format' => config('app.date_format'),'usd_precision' => 2,'bdt_precision' => 2,'pwa_theme_color' => '#435cea','pwa_background_color' => '#e6e9fc',default => null
        };
    }
}
