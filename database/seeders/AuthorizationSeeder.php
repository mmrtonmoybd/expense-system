<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = ['User', 'Role', 'Platform', 'Customer', 'UsdPurchase', 'Sale', 'Invoice', 'InventoryTransaction', 'ExpenseCategory', 'Expense', 'Employee', 'Payroll', 'AuditLog'];
        $permissions = [];
        foreach ($subjects as $subject) {
            $actions = match ($subject) {
                'Invoice', 'InventoryTransaction', 'AuditLog' => ['ViewAny', 'View'],
                'Expense' => ['ViewAny', 'View', 'Create', 'Update'],
                default => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'],
            };

            foreach ($actions as $action) {
                $permissions[] = "{$action}:{$subject}";
            }
        }
        $custom = ['ViewProfit', 'ViewInternalCosts', 'OverrideRate:Sale', 'Post:Sale', 'Approve:Sale', 'Reject:Sale', 'Reverse:Sale', 'Post:UsdPurchase', 'Adjust:Inventory', 'Export:Report', 'View:InventoryAdjustment', 'View:PendingApprovals', 'View:ProfitPredictor', 'View:Reports', 'View:Settings', 'Manage:Settings', 'Manage:CostingMethod', 'Manage:SellRate', 'ViewPayroll', 'Pay:Payroll'];
        foreach ([...$permissions, ...$custom] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $super = Role::findOrCreate('super_admin', 'web');
        $owner = Role::findOrCreate('owner', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $employee = Role::findOrCreate('employee', 'web');
        $normal = Role::findOrCreate('normal_user', 'web');
        $super->syncPermissions(Permission::all());
        $owner->syncPermissions(Permission::all());
        $admin->syncPermissions(Permission::query()->whereNotIn('name', ['Manage:Settings', 'Manage:CostingMethod', 'Delete:User', 'DeleteAny:User', 'Create:User', 'Update:User', 'ViewProfit', 'ViewAny:Role', 'View:Role', 'Create:Role', 'Update:Role', 'Delete:Role', 'DeleteAny:Role'])->get());
        $employee->syncPermissions(Permission::query()->whereIn('name', ['ViewAny:Customer', 'View:Customer', 'Create:Customer', 'Update:Customer', 'ViewAny:Platform', 'View:Platform', 'ViewAny:Sale', 'View:Sale', 'Create:Sale', 'Update:Sale', 'Post:Sale', 'ViewAny:Invoice', 'View:Invoice'])->get());
        $normal->syncPermissions(Permission::query()->whereIn('name', ['ViewAny:Customer', 'View:Customer', 'ViewAny:Platform', 'View:Platform', 'ViewAny:Sale', 'View:Sale', 'Create:Sale', 'ViewAny:Invoice', 'View:Invoice'])->get());
        $adminUser = User::query()->where('email', 'admin@admin.com')->first();
        if ($adminUser && ! $adminUser->roles()->exists()) {
            $adminUser->assignRole($super);
        }
    }
}
