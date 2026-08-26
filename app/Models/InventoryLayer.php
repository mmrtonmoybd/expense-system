<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class InventoryLayer extends Model
{
    protected $fillable = ['origin_type', 'source_type', 'source_id', 'usd_purchase_id', 'purchase_rate', 'original_quantity', 'remaining_quantity', 'original_cost_bdt', 'remaining_cost_bdt', 'is_closed', 'acquired_at', 'created_by', 'note'];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(UsdPurchase::class, 'usd_purchase_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SaleCostAllocation::class);
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_closed', false)->where('remaining_quantity', '>', 0);
    }

    protected function casts(): array
    {
        return ['usd_purchase_id' => 'integer', 'purchase_rate' => 'decimal:8', 'original_quantity' => 'decimal:8', 'remaining_quantity' => 'decimal:8', 'original_cost_bdt' => 'decimal:2', 'remaining_cost_bdt' => 'decimal:2', 'is_closed' => 'boolean', 'acquired_at' => 'datetime'];
    }
}
