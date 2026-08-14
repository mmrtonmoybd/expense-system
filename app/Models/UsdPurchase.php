<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UsdPurchase extends Model
{
    /** @use HasFactory<\Database\Factories\UsdPurchaseFactory> */
    use HasFactory;

    protected $fillable = ['purchase_number', 'purchase_date', 'usd_quantity', 'purchase_rate', 'bdt_total', 'source', 'reference', 'attachment_path', 'note', 'status', 'created_by', 'posted_by', 'posted_at'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'usd_quantity' => 'decimal:8', 'purchase_rate' => 'decimal:8', 'bdt_total' => 'decimal:2', 'posted_at' => 'datetime'];
    }
}
