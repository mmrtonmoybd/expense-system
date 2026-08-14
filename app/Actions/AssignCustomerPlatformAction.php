<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\CustomerPlatformRateHistory;
use App\Models\Platform;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

final readonly class AssignCustomerPlatformAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(
        Customer $customer,
        int $platformId,
        string $sellingRate,
        User $user,
        ?string $reason = null,
    ): CustomerPlatform {
        Gate::forUser($user)->authorize('update', $customer);

        if (bccomp($sellingRate, '0', 8) !== 1) {
            throw new LogicException('Selling rate must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $platformId, $sellingRate, $user, $reason): CustomerPlatform {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());
            Platform::query()->where('is_active', true)->findOrFail($platformId);

            if ($lockedCustomer->customerPlatforms()->where('platform_id', $platformId)->exists()) {
                throw new LogicException('This platform is already attached to the customer.');
            }

            $assignment = $lockedCustomer->customerPlatforms()->create([
                'platform_id' => $platformId,
                'current_selling_rate' => $sellingRate,
                'note' => $reason,
            ]);

            CustomerPlatformRateHistory::query()->create([
                'customer_id' => $lockedCustomer->id,
                'platform_id' => $platformId,
                'new_rate' => $sellingRate,
                'effective_at' => now(),
                'changed_by' => $user->id,
                'reason' => $reason,
            ]);

            $this->auditLogger->record(
                $user,
                'customer_platform_attached',
                $assignment,
                newValues: [
                    'platform_id' => $platformId,
                    'current_selling_rate' => $sellingRate,
                ],
                reason: $reason,
            );

            return $assignment->refresh();
        });
    }
}
