<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CustomerPlatform;
use App\Models\CustomerPlatformRateHistory;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

final readonly class UpdateCustomerPlatformRateAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(CustomerPlatform $assignment, string $newRate, User $user, ?string $reason = null): CustomerPlatform
    {
        if (bccomp($newRate, '0', 8) !== 1) {
            throw new LogicException('Selling rate must be greater than zero.');
        }

        return DB::transaction(function () use ($assignment, $newRate, $user, $reason): CustomerPlatform {
            $assignment = CustomerPlatform::query()->lockForUpdate()->findOrFail($assignment->id);
            $assignment->loadMissing('customer');
            Gate::forUser($user)->authorize('update', $assignment->customer);

            $oldRate = (string) $assignment->current_selling_rate;
            if (bccomp($oldRate, $newRate, 8) === 0) {
                $assignment->update(['note' => $reason]);

                return $assignment->refresh();
            }
            $assignment->update(['current_selling_rate' => $newRate, 'note' => $reason]);
            CustomerPlatformRateHistory::query()->create(['customer_id' => $assignment->customer_id, 'platform_id' => $assignment->platform_id, 'old_rate' => $oldRate, 'new_rate' => $newRate, 'effective_at' => now(), 'changed_by' => $user->id, 'reason' => $reason]);
            $this->auditLogger->record($user, 'customer_platform_rate_changed', $assignment, ['current_selling_rate' => $oldRate], ['current_selling_rate' => $newRate], $reason);

            return $assignment->refresh();
        });
    }
}
