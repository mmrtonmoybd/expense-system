<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Support\AuditLogger;
use App\Support\SettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Settings extends Page
{
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.admin.pages.settings';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:Settings') ?? false;
    }

    public function mount(SettingsRepository $settings): void
    {
        abort_unless(self::canAccess(), 403);
        $keys = ['business_name', 'business_logo', 'business_address', 'business_phone', 'business_email', 'business_website', 'invoice_prefix', 'invoice_footer', 'invoice_terms', 'authorized_person', 'signature_label', 'sale_approval_required', 'timezone', 'date_format', 'usd_precision', 'bdt_precision', 'pwa_app_name', 'pwa_short_name', 'pwa_app_icon', 'pwa_favicon', 'pwa_theme_color', 'pwa_background_color', 'login_logo'];
        $this->form->fill(collect($keys)->mapWithKeys(fn (string $key): array => [$key => $settings->get($key, $this->default($key))])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([Tabs::make('Settings')->tabs([
            Tab::make('Business')->schema([Section::make()->schema([Grid::make(['default' => 1, 'md' => 2])->schema([TextInput::make('business_name')->required(), FileUpload::make('business_logo')->image()->directory('branding')->visibility('public'), Textarea::make('business_address'), TextInput::make('business_phone'), TextInput::make('business_email')->email(), TextInput::make('business_website')->url()])])]),
            Tab::make('Invoice')->schema([Section::make()->schema([Grid::make(['default' => 1, 'md' => 2])->schema([TextInput::make('invoice_prefix')->default('INV'), TextInput::make('authorized_person'), TextInput::make('signature_label'), Textarea::make('invoice_footer'), Textarea::make('invoice_terms')])])]),
            Tab::make('System')->schema([Section::make()->schema([Grid::make(['default' => 1, 'md' => 2])->schema([Toggle::make('sale_approval_required'), Select::make('timezone')->options(['Asia/Dhaka' => 'Asia/Dhaka', 'UTC' => 'UTC'])->required(), Select::make('date_format')->options([(string) config('app.date_format') => now()->format((string) config('app.date_format'))])->default(config('app.date_format'))->required(), Select::make('usd_precision')->options([2 => '2', 4 => '4', 8 => '8']), Select::make('bdt_precision')->options([0 => '0', 2 => '2'])])])]),
            Tab::make('PWA')->schema([Section::make()->schema([Grid::make(['default' => 1, 'md' => 2])->schema([TextInput::make('pwa_app_name')->required(), TextInput::make('pwa_short_name')->required()->maxLength(12), FileUpload::make('pwa_app_icon')->label('Square app icon')->image()->imageEditor()->imageEditorAspectRatioOptions(['1:1'])->directory('branding')->visibility('public')->minSize(100)->maxSize(4096), FileUpload::make('pwa_favicon')->image()->directory('branding')->visibility('public'), ColorPicker::make('pwa_theme_color'), ColorPicker::make('pwa_background_color'), FileUpload::make('login_logo')->image()->directory('branding')->visibility('public')])])]),
        ])->columnSpanFull()]);
    }

    public function save(SettingsRepository $settings, AuditLogger $auditLogger): void
    {
        abort_unless(auth()->user()?->can('Manage:Settings'), 403);
        $settings->putMany($this->form->getState());
        $auditLogger->record(auth()->user(), 'settings_updated', auth()->user());
        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->submit('save')->keyBindings(['mod+s'])->visible(fn (): bool => auth()->user()?->can('Manage:Settings') ?? false)];
    }

    private function default(string $key): mixed
    {
        return match ($key) {
            'business_name','pwa_app_name' => config('app.name'),'pwa_short_name' => 'Agency','invoice_prefix' => 'INV','sale_approval_required' => false,'timezone' => 'Asia/Dhaka','date_format' => config('app.date_format'),'usd_precision' => 2,'bdt_precision' => 2,'pwa_theme_color' => '#435cea','pwa_background_color' => '#e6e9fc',default => null
        };
    }
}
