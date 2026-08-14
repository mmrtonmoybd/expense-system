<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AuditLog');
    }

    public function view(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return $authUser->can('View:AuditLog');
    }
}
