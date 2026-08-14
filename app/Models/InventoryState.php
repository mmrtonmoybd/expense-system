<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class InventoryState extends Model
{
    protected $fillable = ['usd_balance', 'inventory_value_bdt', 'weighted_average_cost'];

    protected function casts(): array
    {
        return ['usd_balance' => 'decimal:8', 'inventory_value_bdt' => 'decimal:2', 'weighted_average_cost' => 'decimal:8'];
    }
}
