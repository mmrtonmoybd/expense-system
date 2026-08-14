<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerPlatform extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerPlatformFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'platform_id', 'current_selling_rate', 'note'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    protected function casts(): array
    {
        return ['current_selling_rate' => 'decimal:8'];
    }
}
