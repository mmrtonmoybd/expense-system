<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Payroll;
use App\Models\PayrollPayment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RecordPayrollPaymentAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(Payroll $payroll, string $amount, User $user, string $paymentDate, ?string $method = null, ?string $reference = null, ?string $note = null): PayrollPayment
    {
        // Round to the precision the column stores so the payroll totals can never drift from
        // the sum of the payment rows.
        $amount = Money::bdt($amount);

        return DB::transaction(function () use ($payroll, $amount, $user, $paymentDate, $method, $reference, $note): PayrollPayment {
            $payroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            if (Money::compareBdt($amount, '0') !== 1 || Money::compareBdt($amount, (string) $payroll->due_amount) === 1) {
                throw new LogicException('Payment must be positive and cannot exceed salary due.');
            }
            $payment = PayrollPayment::query()->create(['payroll_id' => $payroll->id, 'amount' => $amount, 'payment_date' => $paymentDate, 'method' => $method, 'reference' => $reference, 'note' => $note, 'created_by' => $user->id]);
            $paid = Money::addBdt((string) $payroll->paid_amount, $amount);
            $due = Money::subBdt((string) $payroll->total_salary_expense, $paid);
            $payroll->update(['paid_amount' => $paid, 'due_amount' => $due, 'payment_date' => $paymentDate, 'status' => Money::isZero($due, Money::BdtScale) ? 'paid' : 'partially_paid']);
            $this->auditLogger->record($user, 'payroll_payment_recorded', $payroll, newValues: ['payment_id' => $payment->id, 'amount' => $amount]);

            return $payment;
        });
    }
}
