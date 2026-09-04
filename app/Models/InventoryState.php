<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class InventoryState extends Model
{
    protected $fillable = ['usd_balance', 'inventory_value_bdt', 'weighted_average_cost'];

    /**
     * The one and only inventory position, with a write lock held for the rest of the
     * transaction so concurrent postings queue instead of racing.
     */
    public static function locked(): self
    {
        return self::query()->lockForUpdate()->firstOr(fn (): self => self::query()->create([
            'usd_balance' => '0',
            'inventory_value_bdt' => '0',
            'weighted_average_cost' => '0',
        ]));
    }

    /** The current position for reading, without creating anything. */
    public static function current(): ?self
    {
        return self::query()->first();
    }

    protected function casts(): array
    {
        return ['usd_balance' => 'decimal:8', 'inventory_value_bdt' => 'decimal:2', 'weighted_average_cost' => 'decimal:8'];
    }
}
