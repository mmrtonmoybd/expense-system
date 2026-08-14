<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Platform extends Model
{
    /** @use HasFactory<\Database\Factories\PlatformFactory> */
    use HasFactory;

    protected $fillable = ['name', 'short_name', 'logo_path', 'description', 'is_active', 'sort_order'];

    public function customerPlatforms(): HasMany
    {
        return $this->hasMany(CustomerPlatform::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
