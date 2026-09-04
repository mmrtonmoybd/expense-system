<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Keeps operator-supplied text from being evaluated when an export is opened.
 *
 * Report rows carry free text (customer names, notes, reasons). A spreadsheet treats a cell
 * starting with =, +, - or @ as a formula, so those values are prefixed with an apostrophe.
 * Plain numbers are left untouched so negative amounts stay numeric.
 */
final class SpreadsheetValue
{
    private const array Triggers = ['=', '+', '-', '@', "\t", "\r"];

    public static function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], self::Triggers, true) ? "'".$value : $value;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    public static function safeRow(array $values): array
    {
        return array_map(self::safe(...), $values);
    }
}
