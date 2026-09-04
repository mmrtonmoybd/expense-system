<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Which financial columns a report may expose to the person requesting it.
 *
 * Report tables hide inventory cost and profit from users who lack the matching permission,
 * so the report builder and its exports must honour exactly the same rules. Without an actor
 * (console, queued jobs, internal calls) everything stays visible.
 */
final readonly class ReportVisibility
{
    public function __construct(
        public bool $costs = true,
        public bool $profit = true,
        public bool $payroll = true,
        public bool $audit = true,
    ) {}

    public static function for(?Authorizable $user): self
    {
        if ($user === null) {
            return new self;
        }

        return new self(
            costs: (bool) $user->can('ViewInternalCosts'),
            profit: (bool) $user->can('ViewProfit'),
            payroll: (bool) $user->can('ViewPayroll'),
            audit: (bool) $user->can('ViewAny:AuditLog'),
        );
    }

    /** @return array<string, string> */
    public static function catalogue(): array
    {
        return [
            'sales' => 'USD Sales',
            'sale_lines' => 'Sales by Platform Line',
            'purchases' => 'USD Purchases',
            'invoices' => 'Invoices',
            'inventory' => 'Inventory Ledger',
            'inventory_valuation' => 'Inventory Valuation',
            'platforms' => 'Platform Performance',
            'customers' => 'Customer Performance',
            'customer_statement' => 'Customer Statement',
            'rate_history' => 'Customer Rate History',
            'expenses' => 'Expenses',
            'payroll' => 'Payroll',
            'employee_statement' => 'Employee Salary Statement',
            'pnl' => 'Profit & Loss',
            'monthly_summary' => 'Monthly Business Summary',
            'audit' => 'Audit Log',
        ];
    }

    /** Reports whose entire purpose is a restricted figure. */
    public function allowsReport(string $type): bool
    {
        return match ($type) {
            'pnl', 'monthly_summary' => $this->profit,
            'inventory', 'inventory_valuation' => $this->costs,
            'payroll', 'employee_statement' => $this->payroll,
            'audit' => $this->audit,
            default => true,
        };
    }

    /**
     * Every report this user may open, keyed by type.
     *
     * @return array<string, string>
     */
    public function allowedReports(): array
    {
        return collect(self::catalogue())
            ->filter(fn (string $label, string $type): bool => $this->allowsReport($type))
            ->all();
    }
}
