<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CostingMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaleCostAllocation extends Model
{
    protected $fillable = ['sale_id', 'sale_line_id', 'inventory_layer_id', 'usd_purchase_id', 'costing_method', 'quantity_used', 'purchase_rate', 'cost_amount', 'consumed_at'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(InventoryLayer::class, 'inventory_layer_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(UsdPurchase::class, 'usd_purchase_id');
    }

    protected function casts(): array
    {
        return ['costing_method' => CostingMethod::class, 'quantity_used' => 'decimal:8', 'purchase_rate' => 'decimal:8', 'cost_amount' => 'decimal:2', 'consumed_at' => 'datetime'];
    }
}
