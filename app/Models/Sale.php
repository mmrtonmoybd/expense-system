<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CostingMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Sale extends Model
{
    /** @use HasFactory<\Database\Factories\SaleFactory> */
    use HasFactory;

    protected $fillable = ['sale_number', 'invoice_number', 'sale_date', 'customer_id', 'total_usd', 'total_bdt_revenue', 'total_inventory_cost', 'total_gross_profit', 'status', 'approval_status', 'note', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason', 'posted_at', 'costing_method', 'cost_rate', 'cost_amount', 'sell_rate', 'sell_amount', 'profit_amount', 'costing_calculated_at'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function costAllocations(): HasMany
    {
        return $this->hasMany(SaleCostAllocation::class);
    }

    protected function casts(): array
    {
        return ['sale_date' => 'date', 'total_usd' => 'decimal:8', 'total_bdt_revenue' => 'decimal:2', 'total_inventory_cost' => 'decimal:2', 'total_gross_profit' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'posted_at' => 'datetime', 'costing_method' => CostingMethod::class, 'cost_rate' => 'decimal:8', 'cost_amount' => 'decimal:2', 'sell_rate' => 'decimal:8', 'sell_amount' => 'decimal:2', 'profit_amount' => 'decimal:2', 'costing_calculated_at' => 'datetime'];
    }
}
