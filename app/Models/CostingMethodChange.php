<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CostingMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CostingMethodChange extends Model
{
    protected $fillable = ['old_method', 'new_method', 'changed_by', 'changed_at', 'usd_balance_snapshot', 'inventory_value_snapshot', 'average_cost_snapshot', 'opening_layer_id', 'reason'];

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function openingLayer(): BelongsTo
    {
        return $this->belongsTo(InventoryLayer::class, 'opening_layer_id');
    }

    protected function casts(): array
    {
        return ['old_method' => CostingMethod::class, 'new_method' => CostingMethod::class, 'changed_at' => 'datetime', 'usd_balance_snapshot' => 'decimal:8', 'inventory_value_snapshot' => 'decimal:2', 'average_cost_snapshot' => 'decimal:8'];
    }
}
