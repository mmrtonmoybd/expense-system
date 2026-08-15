@php
    $settings = app(\App\Support\SettingsRepository::class);
    $businessName = (string) $settings->get('business_name', config('app.name'));
    $logo = $settings->get('login_logo') ?: $settings->get('business_logo');
@endphp

<div class="office-brand">
    @if ($logo)
        <img
            src="{{ asset('storage/' . $logo) }}"
            alt="{{ $businessName }}"
            class="office-brand__image"
        >
    @else
        <img
            src="{{ asset('icons/app-icon.svg') }}"
            alt="{{ $businessName }}"
            class="office-brand__icon"
        >
        <span class="office-brand__name">{{ $businessName }}</span>
    @endif
</div>
