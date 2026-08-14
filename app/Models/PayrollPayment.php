<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PayrollPayment extends Model
{
    /** @use HasFactory<\Database\Factories\PayrollPaymentFactory> */
    use HasFactory;

    protected $fillable = ['payroll_id', 'amount', 'payment_date', 'method', 'reference', 'note', 'created_by'];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_date' => 'date'];
    }
}
