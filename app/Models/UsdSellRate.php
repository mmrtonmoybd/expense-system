<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UsdSellRate extends Model
{
    protected $fillable = ['rate', 'previous_rate', 'effective_from', 'changed_by', 'note'];

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return ['rate' => 'decimal:8', 'previous_rate' => 'decimal:8', 'effective_from' => 'datetime'];
    }
}
