<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    public function record(User $user, string $action, Model $model, ?array $oldValues = null, ?array $newValues = null, ?string $reason = null): AuditLog
    {
        return AuditLog::query()->create(['user_id' => $user->id, 'action' => $action, 'auditable_type' => $model::class, 'auditable_id' => $model->getKey(), 'old_values' => $oldValues, 'new_values' => $newValues, 'reason' => $reason, 'ip_address' => request()?->ip(), 'occurred_at' => now()]);
    }
}
