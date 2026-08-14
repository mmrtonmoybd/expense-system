<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

final class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'company_name', 'address', 'notes', 'is_active', 'created_by'];

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'customer_platforms')->withPivot('current_selling_rate', 'note')->withTimestamps();
    }

    public function customerPlatforms(): HasMany
    {
        return $this->hasMany(CustomerPlatform::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(Invoice::class, Sale::class);
    }

    public function rateHistories(): HasMany
    {
        return $this->hasMany(CustomerPlatformRateHistory::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
