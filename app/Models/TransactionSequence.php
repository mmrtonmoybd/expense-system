<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class TransactionSequence extends Model
{
    protected $fillable = ['type', 'period', 'last_number'];
}
