<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Invoice extends Model
{
    protected $fillable = ['sale_id', 'invoice_number', 'invoice_date', 'status', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'cancelled_at' => 'datetime'];
    }
}
