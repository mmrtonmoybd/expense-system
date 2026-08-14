<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Customer');
    }

    public function view(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('View:Customer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Customer');
    }

    public function update(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('Update:Customer');
    }

    public function delete(AuthUser $authUser, Customer $customer): bool
    {
        return $authUser->can('Delete:Customer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Customer');
    }
}
