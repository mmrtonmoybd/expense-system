<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

/**
 * Language handling for the panel and for the documents it prints.
 *
 * Bengali needs more than translated strings: amounts, quantities and dates are read in
 * Bengali-Indic digits, so every number that reaches a document goes through here rather than
 * straight out of number_format().
 */
final class Localization
{
    public const string SettingKey = 'app_locale';

    public const string SessionKey = 'app_locale';

    public const string Fallback = 'en';

    private const array BengaliDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    /** @return array<string, string> */
    public static function supported(): array
    {
        return [
            'en' => 'English',
            'bn' => 'বাংলা',
        ];
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::supported());
    }

    public static function current(): string
    {
        $locale = App::getLocale();

        return self::isSupported($locale) ? $locale : self::Fallback;
    }

    public static function isBengali(): bool
    {
        return self::current() === 'bn';
    }

    /** The BCP 47 tag for the document's `lang` attribute. */
    public static function tag(): string
    {
        return self::isBengali() ? 'bn-BD' : 'en';
    }

    /** Western digits rewritten in the current locale's numerals. */
    public static function digits(string $value): string
    {
        if (! self::isBengali()) {
            return $value;
        }

        return str_replace(range('0', '9'), self::BengaliDigits, $value);
    }

    /** A formatted number in the current locale's numerals. */
    public static function number(string|int|float|null $value, int $decimals = 2): string
    {
        return self::digits(number_format((float) $value, $decimals));
    }

    /** A money amount with the taka sign, in the current locale's numerals. */
    public static function money(string|int|float|null $value, int $decimals = 2): string
    {
        return '৳ '.self::number($value, $decimals);
    }

    /** A date rendered with translated month names and localised numerals. */
    public static function date(Carbon|string|null $date, ?string $format = null): string
    {
        if ($date === null) {
            return '';
        }

        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $format ??= (string) config('app.date_format');

        return self::digits($date->locale(self::current())->translatedFormat($format));
    }

    public static function dateTime(Carbon|string|null $date, ?string $format = null): string
    {
        return self::date($date, $format ?? (string) config('app.datetime_format'));
    }

    /**
     * Split the two-part Bengali vowels so a PDF can lay them out.
     *
     * ো and ৌ are single codepoints that have to be drawn on both sides of the consonant they
     * attach to. DomPDF does not perform that split, so it draws the two halves side by side
     * with a gap. Feeding it the canonical decomposition instead — ে plus া, ে plus ৗ — gives
     * the shaper the two pieces it already knows how to place. Browsers render either form,
     * so only the PDF path needs this.
     */
    public static function decomposeBengaliVowels(string $text): string
    {
        return str_replace(
            ["\u{09CB}", "\u{09CC}"],
            ["\u{09C7}\u{09BE}", "\u{09C7}\u{09D7}"],
            $text,
        );
    }

    /** Absolute path of the Bengali-capable font the documents embed. */
    public static function documentFontPath(string $weight = 'Regular'): string
    {
        return public_path("fonts/NotoSansBengali-{$weight}.ttf");
    }
}
