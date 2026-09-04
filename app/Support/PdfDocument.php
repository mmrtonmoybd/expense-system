<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWriter;

/**
 * Renders a Blade document to PDF with the text prepared for the PDF engine.
 *
 * Everything that reaches a PDF goes through here so the Bengali vowel handling is applied
 * once, to the finished HTML, rather than being repeated at every field in every template.
 */
final class PdfDocument
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data): PdfWriter
    {
        $html = view($view, $data)->render();

        return Pdf::loadHTML(Localization::decomposeBengaliVowels($html));
    }
}
