<?php

declare(strict_types=1);

namespace App\Support;

final class PayrollCalculator
{
    /** @param array{base_salary:mixed, allowance?:mixed, bonus?:mixed, deduction?:mixed, adjustment?:mixed} $data */
    public function total(array $data): string
    {
        return bcadd(
            bcsub(
                bcadd(
                    bcadd((string) $data['base_salary'], (string) ($data['allowance'] ?? 0), 2),
                    (string) ($data['bonus'] ?? 0),
                    2,
                ),
                (string) ($data['deduction'] ?? 0),
                2,
            ),
            (string) ($data['adjustment'] ?? 0),
            2,
        );
    }
}
