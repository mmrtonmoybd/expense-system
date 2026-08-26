<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CostingMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SaleLine extends Model
{
    protected $fillable = ['sale_id', 'platform_id', 'usd_quantity', 'selling_rate', 'weighted_cost_rate', 'revenue', 'inventory_cost', 'gross_profit', 'is_rate_overridden', 'rate_overridden_by', 'rate_override_reason', 'note', 'costing_method'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function costAllocations(): HasMany
    {
        return $this->hasMany(SaleCostAllocation::class, 'sale_line_id');
    }

    protected function casts(): array
    {
        return ['usd_quantity' => 'decimal:8', 'selling_rate' => 'decimal:8', 'weighted_cost_rate' => 'decimal:8', 'revenue' => 'decimal:2', 'inventory_cost' => 'decimal:2', 'gross_profit' => 'decimal:2', 'is_rate_overridden' => 'boolean', 'costing_method' => CostingMethod::class];
    }
}
