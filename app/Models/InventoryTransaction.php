<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class InventoryTransaction extends Model
{
    protected $fillable = ['transaction_type', 'source_type', 'source_id', 'usd_in', 'usd_out', 'cost_rate', 'bdt_value_in', 'bdt_value_out', 'resulting_usd_balance', 'resulting_inventory_value_bdt', 'resulting_weighted_average_cost', 'created_by', 'occurred_at', 'note'];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['usd_in' => 'decimal:8', 'usd_out' => 'decimal:8', 'cost_rate' => 'decimal:8', 'bdt_value_in' => 'decimal:2', 'bdt_value_out' => 'decimal:2', 'resulting_usd_balance' => 'decimal:8', 'resulting_inventory_value_bdt' => 'decimal:2', 'resulting_weighted_average_cost' => 'decimal:8', 'occurred_at' => 'datetime'];
    }
}
