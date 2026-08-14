<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TransactionSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TransactionNumberGenerator
{
    public function next(string $type, ?string $date = null): string
    {
        $prefix = ['purchase' => 'PUR', 'sale' => 'SAL', 'invoice' => 'INV', 'expense' => 'EXP', 'payroll' => 'PAYROLL'][$type] ?? throw new InvalidArgumentException('Unknown transaction number type.');
        $period = now()->parse($date)->format('Ym');

        return DB::transaction(function () use ($type, $prefix, $period): string {
            TransactionSequence::query()->insertOrIgnore(['type' => $type, 'period' => $period, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = TransactionSequence::query()->where('type', $type)->where('period', $period)->lockForUpdate()->sole();
            $sequence->increment('last_number');

            return $prefix.'-'.$period.'-'.mb_str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        }, attempts: 5);
    }
}
