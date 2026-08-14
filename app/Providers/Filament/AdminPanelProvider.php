<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Support\SettingsRepository;
use Awcodes\Overlook\OverlookPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use CharrafiMed\GlobalSearchModal\GlobalSearchModalPlugin;
use DutchCodingCompany\FilamentDeveloperLogins\FilamentDeveloperLoginsPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color as FilamentColor;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Openplain\FilamentShadcnTheme\Color;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->authGuard('web')
            ->spa()
            ->spaUrlExceptions([
                Dashboard::class,
            ])
            ->login()
            ->brandName(fn (): string => (string) app(SettingsRepository::class)->get('business_name', config('app.name')))
            ->brandLogo(function (): ?string {
                $logo = app(SettingsRepository::class)->get('login_logo') ?: app(SettingsRepository::class)->get('business_logo');

                return $logo ? asset('storage/'.$logo) : null;
            })
            // ->topbar(false)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->maxContentWidth(Width::Full)
            ->databaseTransactions()
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('pwa.head')->render())
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): string => view('pwa.install-button')->render())
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('pwa.offline-indicator')->render())
            ->assets([
                Js::make('pwa-install', resource_path('js/pwa-install.js')),
            ])
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::adaptive(
                    lightColor: FilamentColor::Blue,
                    darkColor: FilamentColor::Sky
                ),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                //
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('General'),
                NavigationGroup::make()->label('Sales'),
                NavigationGroup::make()->label('Customers'),
                NavigationGroup::make()->label('Inventory'),
                NavigationGroup::make()->label('Expenses'),
                NavigationGroup::make()->label('Payroll'),
                NavigationGroup::make()->label('Reports'),
                NavigationGroup::make()->label('Administration'),
            ])
            ->plugins([
                AuthDesignerPlugin::make()
                    ->login(fn ($config) => $config
                        ->media('https://images.pexels.com/photos/466685/pexels-photo-466685.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2')
                        ->mediaPosition(MediaPosition::Left)
                        ->mediaSize('70%')
                        ->blur(1)
                    )
                    ->themeToggle('90%', '50%'),
                BreezyCore::make()
                    ->myProfile(
                        hasAvatars: true,
                        slug: 'profile',
                        userMenuLabel: 'Profile',
                    )
                    ->enableBrowserSessions(),
                GlobalSearchModalPlugin::make(),
                OverlookPlugin::make()
                    ->sort(2)
                    ->columns([
                        'default' => 4,
                        'sm' => 2,
                        'lg' => 4,
                        'xl' => 6,
                    ])
                    ->includes([
                        UserResource::class,
                    ]),
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 2,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 2,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 2,
                    ])
                    ->navigationLabel('Roles & Permissions')
                    ->navigationGroup('Administration')
                    ->navigationSort(2)
                    ->navigationIcon(Heroicon::ShieldCheck),
                FilamentDeveloperLoginsPlugin::make()
                    ->enabled(app()->environment('local'))
                    ->switchable(true)
                    ->users(fn () => User::pluck('email', 'name')->toArray()),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
