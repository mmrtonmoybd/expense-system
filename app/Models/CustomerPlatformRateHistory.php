<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerPlatformRateHistory extends Model
{
    protected $fillable = ['customer_id', 'platform_id', 'old_rate', 'new_rate', 'effective_at', 'changed_by', 'reason'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return ['old_rate' => 'decimal:8', 'new_rate' => 'decimal:8', 'effective_at' => 'datetime'];
    }
}
