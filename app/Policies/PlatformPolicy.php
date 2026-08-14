<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Platform;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class PlatformPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Platform');
    }

    public function view(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('View:Platform');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Platform');
    }

    public function update(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('Update:Platform');
    }

    public function delete(AuthUser $authUser, Platform $platform): bool
    {
        return $authUser->can('Delete:Platform');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Platform');
    }
}
