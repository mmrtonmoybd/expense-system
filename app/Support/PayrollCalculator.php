<?php

declare(strict_types=1);

namespace App\Support;

final class PayrollCalculator
{
    /** @param array{base_salary:mixed, allowance?:mixed, bonus?:mixed, deduction?:mixed, adjustment?:mixed} $data */
    public function total(array $data): string
    {
        $total = Money::addBdt((string) $data['base_salary'], (string) ($data['allowance'] ?? 0));
        $total = Money::addBdt($total, (string) ($data['bonus'] ?? 0));
        $total = Money::subBdt($total, (string) ($data['deduction'] ?? 0));

        return Money::addBdt($total, (string) ($data['adjustment'] ?? 0));
    }
}
