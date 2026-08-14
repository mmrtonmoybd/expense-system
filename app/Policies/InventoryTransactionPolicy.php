<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class InventoryTransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryTransaction');
    }

    public function view(AuthUser $authUser, InventoryTransaction $inventoryTransaction): bool
    {
        return $authUser->can('View:InventoryTransaction');
    }
}
