<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_private'];

    protected function casts(): array
    {
        return ['is_private' => 'boolean'];
    }
}
