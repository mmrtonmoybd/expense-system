<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payroll;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class PayrollPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Payroll');
    }

    public function view(AuthUser $authUser, Payroll $payroll): bool
    {
        return $authUser->can('View:Payroll');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Payroll');
    }

    public function update(AuthUser $authUser, Payroll $payroll): bool
    {
        return $authUser->can('Update:Payroll');
    }

    public function delete(AuthUser $authUser, Payroll $payroll): bool
    {
        return $authUser->can('Delete:Payroll');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Payroll');
    }
}
