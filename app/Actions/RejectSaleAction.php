<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Sale;
use App\Models\User;
use App\Support\AuditLogger;
use LogicException;

final readonly class RejectSaleAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(Sale $sale, User $user, string $reason): Sale
    {
        if ($sale->status !== 'pending' || blank($reason)) {
            throw new LogicException('Pending sale and rejection reason are required.');
        }
        $sale->update(['status' => 'rejected', 'approval_status' => 'rejected', 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
        $this->auditLogger->record($user, 'sale_rejected', $sale, reason: $reason);

        return $sale->refresh();
    }
}
