<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Payroll;
use App\Models\PayrollPayment;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RecordPayrollPaymentAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(Payroll $payroll, string $amount, User $user, string $paymentDate, ?string $method = null, ?string $reference = null, ?string $note = null): PayrollPayment
    {
        return DB::transaction(function () use ($payroll, $amount, $user, $paymentDate, $method, $reference, $note): PayrollPayment {
            $payroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            if (bccomp($amount, '0', 2) !== 1 || bccomp($amount, (string) $payroll->due_amount, 2) === 1) {
                throw new LogicException('Payment must be positive and cannot exceed salary due.');
            }
            $payment = PayrollPayment::query()->create(['payroll_id' => $payroll->id, 'amount' => $amount, 'payment_date' => $paymentDate, 'method' => $method, 'reference' => $reference, 'note' => $note, 'created_by' => $user->id]);
            $paid = bcadd((string) $payroll->paid_amount, $amount, 2);
            $due = bcsub((string) $payroll->total_salary_expense, $paid, 2);
            $payroll->update(['paid_amount' => $paid, 'due_amount' => $due, 'payment_date' => $paymentDate, 'status' => bccomp($due, '0', 2) === 0 ? 'paid' : 'partially_paid']);
            $this->auditLogger->record($user, 'payroll_payment_recorded', $payroll, newValues: ['payment_id' => $payment->id, 'amount' => $amount]);

            return $payment;
        });
    }
}
