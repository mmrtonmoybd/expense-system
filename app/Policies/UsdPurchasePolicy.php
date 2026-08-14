<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UsdPurchase;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class UsdPurchasePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UsdPurchase');
    }

    public function view(AuthUser $authUser, UsdPurchase $usdPurchase): bool
    {
        return $authUser->can('View:UsdPurchase');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UsdPurchase');
    }

    public function update(AuthUser $authUser, UsdPurchase $usdPurchase): bool
    {
        return $authUser->can('Update:UsdPurchase');
    }

    public function delete(AuthUser $authUser, UsdPurchase $usdPurchase): bool
    {
        return $authUser->can('Delete:UsdPurchase');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:UsdPurchase');
    }
}
