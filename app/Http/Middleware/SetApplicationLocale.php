<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Localization;
use App\Support\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the language for the request.
 *
 * A `?lang=` parameter wins, so an operator can print one invoice in Bengali without changing
 * the panel; the choice is remembered for the session. Otherwise the business-wide default
 * from Settings applies.
 */
final readonly class SetApplicationLocale
{
    public function __construct(private SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');

        if (Localization::isSupported(is_string($requested) ? $requested : null)) {
            $request->session()?->put(Localization::SessionKey, $requested);
        }

        $locale = collect([
            $request->session()?->get(Localization::SessionKey),
            $this->settings->get(Localization::SettingKey),
        ])
            ->first(fn (mixed $candidate): bool => Localization::isSupported(is_string($candidate) ? $candidate : null))
            ?? Localization::Fallback;

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
