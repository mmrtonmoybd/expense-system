<link rel="manifest" href="{{ route('pwa.manifest') }}" />
<meta
    name="theme-color"
    content="{{ app(\App\Support\SettingsRepository::class)->get('pwa_theme_color', '#435cea') }}"
/>
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
@php($icon = app(\App\Support\SettingsRepository::class)->get('pwa_app_icon'))
<link
    rel="apple-touch-icon"
    sizes="180x180"
    href="{{ $icon ? route('pwa.icon', 180) : asset('icons/app-icon.svg') }}"
/>
<link rel="icon" href="{{ $icon ? route('pwa.icon', 64) : asset('icons/app-icon.svg') }}" />
