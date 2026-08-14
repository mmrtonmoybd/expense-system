<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

final readonly class ReportExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private array $report) {}

    public function array(): array
    {
        return $this->report['rows'];
    }

    public function headings(): array
    {
        return $this->report['headings'];
    }
}
