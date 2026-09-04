<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\PostSaleAction;
use App\Actions\PostUsdPurchaseAction;
use App\Actions\RecordPayrollPaymentAction;
use App\Actions\ReverseSaleAction;
use App\Actions\UpdateCustomerPlatformRateAction;
use App\Models\Customer;
use App\Models\CustomerPlatform;
use App\Models\CustomerPlatformRateHistory;
use App\Models\Employee;
use App\Models\EmployeeSalaryHistory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payroll;
use App\Models\Platform;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Setting;
use App\Models\UsdPurchase;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollCalculator;
use App\Support\TransactionNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'demo'])) {
            throw new RuntimeException('Demo data may only be seeded in local, testing, or demo environments.');
        }
        if (Setting::query()->where('key', 'demo_seeded_at')->exists()) {
            throw new RuntimeException('Demo data already exists.');
        }
        $this->call(AuthorizationSeeder::class);
        $password = Hash::make('password');
        $users = collect(['super@demo.test' => 'super_admin', 'owner@demo.test' => 'owner', 'admin@demo.test' => 'admin', 'employee@demo.test' => 'employee', 'user@demo.test' => 'normal_user'])->mapWithKeys(function (string $role, string $email) use ($password): array {
            $user = User::query()->updateOrCreate(['email' => $email], ['name' => str($role)->replace('_', ' ')->title(), 'password' => $password]);
            $user->syncRoles([$role]);

            return [$role => $user];
        });
        $actor = $users['super_admin'];
        $platforms = collect([['Facebook / Meta', 'META'], ['TikTok', 'TIKTOK'], ['Google Ads', 'GOOGLE']])->mapWithKeys(fn (array $item, int $index): array => [$item[1] => Platform::query()->updateOrCreate(['name' => $item[0]], ['short_name' => $item[1], 'is_active' => true, 'sort_order' => $index + 1])]);
        $names = ['ABC Trading', 'Dhaka Fashion House', 'Nobanno Foods', 'Bright Electronics', 'Green Leaf Tours', 'Ruposhi Boutique', 'Tech Horizon', 'Urban Furniture', 'Meghna Properties', 'Padma Pharmacy', 'Chattogram Traders', 'Sylhet Tea Corner', 'Barishal Mart', 'Rajshahi Agro', 'Khulna Digital'];
        $customers = collect($names)->map(function (string $name, int $index) use ($actor): Customer {
            return Customer::query()->create(['name' => $name, 'phone' => '017'.mb_str_pad((string) (12000000 + $index), 8, '0', STR_PAD_LEFT), 'email' => $index % 3 === 0 ? null : 'customer'.($index + 1).'@example.test', 'company_name' => $index % 2 === 0 ? $name.' Ltd.' : null, 'address' => 'Dhaka, Bangladesh', 'is_active' => true, 'created_by' => $actor->id]);
        });
        foreach ($customers as $customerIndex => $customer) {
            foreach ($platforms->values() as $platformIndex => $platform) {
                if (($customerIndex + $platformIndex) % 4 === 0) {
                    continue;
                }$rate = (string) (118 + (($customerIndex * 2 + $platformIndex) % 13));
                $assignment = CustomerPlatform::query()->create(['customer_id' => $customer->id, 'platform_id' => $platform->id, 'current_selling_rate' => $rate]);
                CustomerPlatformRateHistory::query()->create(['customer_id' => $customer->id, 'platform_id' => $platform->id, 'new_rate' => $rate, 'effective_at' => now()->subMonths(3), 'changed_by' => $actor->id, 'reason' => 'Initial demo rate']);
                if ($customerIndex < 5) {
                    app(UpdateCustomerPlatformRateAction::class)->execute($assignment, bcadd($rate, '1.50', 2), $actor, 'Quarterly rate update');
                }
            }
        }
        foreach ([[-5, 5000, 111], [-4, 3000, 113], [-3, 7500, 112.5], [-2, 4000, 115]] as [$months,$quantity,$rate]) {
            $date = now()->addMonths($months)->toDateString();
            $purchase = UsdPurchase::query()->create(['purchase_number' => app(TransactionNumberGenerator::class)->next('purchase', $date), 'purchase_date' => $date, 'usd_quantity' => $quantity, 'purchase_rate' => $rate, 'bdt_total' => 0, 'source' => 'Demo USD supplier', 'status' => 'draft', 'created_by' => $actor->id]);
            app(PostUsdPurchaseAction::class)->execute($purchase, $actor);
        }
        $posted = [];
        foreach (range(0, 17) as $index) {
            $customer = $customers[$index % $customers->count()];
            $rates = $customer->customerPlatforms()->get();
            if ($rates->isEmpty()) {
                continue;
            }$date = now()->subMonths(5 - ($index % 6))->subDays($index % 20)->toDateString();
            $sale = Sale::query()->create(['sale_number' => app(TransactionNumberGenerator::class)->next('sale', $date), 'sale_date' => $date, 'customer_id' => $customer->id, 'status' => 'draft', 'approval_status' => 'not_required', 'created_by' => $actor->id]);
            foreach ($rates->take($index % 3 === 0 ? 2 : 1) as $lineIndex => $assignment) {
                SaleLine::query()->create(['sale_id' => $sale->id, 'platform_id' => $assignment->platform_id, 'usd_quantity' => (string) (150 + (($index + $lineIndex) * 25) % 350), 'selling_rate' => $assignment->current_selling_rate, 'weighted_cost_rate' => 0, 'revenue' => 0, 'inventory_cost' => 0, 'gross_profit' => 0]);
            }$posted[] = app(PostSaleAction::class)->execute($sale, $actor);
        }
        if ($posted) {
            app(ReverseSaleAction::class)->execute($posted[0], $actor, 'Demo reversal example');
        }
        foreach (['Office Rent', 'Electricity', 'Internet', 'Transport', 'Food', 'Software', 'Hosting', 'Marketing', 'Equipment', 'Maintenance', 'Bank Charge', 'Mobile Banking Charge', 'Tax', 'Other'] as $index => $name) {
            ExpenseCategory::query()->updateOrCreate(['name' => $name], ['is_active' => true, 'sort_order' => $index + 1]);
        }
        $categories = ExpenseCategory::query()->get();
        foreach (range(0, 35) as $index) {
            $date = now()->subMonths($index % 6)->subDays(($index * 3) % 24)->toDateString();
            $category = $categories[$index % $categories->count()];
            Expense::query()->create(['expense_number' => app(TransactionNumberGenerator::class)->next('expense', $date), 'expense_date' => $date, 'expense_category_id' => $category->id, 'amount' => (string) (1200 + (($index * 875) % 28000)), 'description' => $category->name.' demo expense', 'vendor' => 'Demo Vendor '.(($index % 5) + 1), 'created_by' => $actor->id]);
        }
        $employees = collect([['EMP-001', 'Amina Rahman', 'Accountant', 42000], ['EMP-002', 'Sakib Hasan', 'Media Buyer', 38000], ['EMP-003', 'Nusrat Jahan', 'Client Manager', 45000], ['EMP-004', 'Rafi Ahmed', 'Designer', 35000], ['EMP-005', 'Mim Akter', 'Sales Executive', 32000], ['EMP-006', 'Tanvir Islam', 'Office Assistant', 24000], ['EMP-007', 'Farhana Yasmin', 'Operations Manager', 55000]])->map(function (array $item, int $index) use ($actor): Employee {
            $employee = Employee::query()->create(['employee_code' => $item[0], 'name' => $item[1], 'phone' => '018'.mb_str_pad((string) (13000000 + $index), 8, '0', STR_PAD_LEFT), 'email' => $index % 2 ? 'employee'.($index + 1).'@example.test' : null, 'designation' => $item[2], 'joining_date' => now()->subYears(2)->addMonths($index)->toDateString(), 'current_salary' => $item[3], 'is_active' => true]);
            EmployeeSalaryHistory::query()->create(['employee_id' => $employee->id, 'new_salary' => $item[3], 'effective_at' => now()->subYear(), 'changed_by' => $actor->id, 'reason' => 'Initial demo salary']);

            return $employee;
        });
        foreach ($employees as $employeeIndex => $employee) {
            foreach (range(3, 1) as $monthOffset) {
                $month = now()->subMonths($monthOffset)->startOfMonth()->toDateString();
                $bonus = $employeeIndex === 0 && $monthOffset === 1 ? 5000 : 0;
                $deduction = $employeeIndex === 3 && $monthOffset === 2 ? 1000 : 0;
                $total = app(PayrollCalculator::class)->total(['base_salary' => (string) $employee->current_salary, 'bonus' => (string) $bonus, 'deduction' => (string) $deduction]);
                $payroll = Payroll::query()->create(['payroll_number' => app(TransactionNumberGenerator::class)->next('payroll', $month), 'employee_id' => $employee->id, 'payroll_month' => $month, 'base_salary' => $employee->current_salary, 'allowance' => 0, 'bonus' => $bonus, 'deduction' => $deduction, 'adjustment' => 0, 'total_salary_expense' => $total, 'paid_amount' => 0, 'due_amount' => $total, 'status' => 'unpaid']);
                $payAmount = match (($employeeIndex + $monthOffset) % 3) {
                    0 => $total,1 => Money::amount($total, '0.70'),default => '0'
                };
                if (Money::compareBdt($payAmount, '0') === 1) {
                    app(RecordPayrollPaymentAction::class)->execute($payroll, $payAmount, $actor, now()->subMonths($monthOffset)->endOfMonth()->toDateString(), 'Bank', 'DEMO-PAY');
                }
            }
        }
        $pendingCustomer = $customers->last();
        $pendingRate = $pendingCustomer->customerPlatforms()->first();
        if ($pendingRate) {
            $pending = Sale::query()->create(['sale_number' => app(TransactionNumberGenerator::class)->next('sale'), 'sale_date' => today(), 'customer_id' => $pendingCustomer->id, 'status' => 'pending', 'approval_status' => 'pending', 'created_by' => $users['employee']->id, 'submitted_by' => $users['employee']->id, 'submitted_at' => now(), 'total_usd' => 250, 'total_bdt_revenue' => Money::amount('250', (string) $pendingRate->current_selling_rate)]);
            SaleLine::query()->create(['sale_id' => $pending->id, 'platform_id' => $pendingRate->platform_id, 'usd_quantity' => 250, 'selling_rate' => $pendingRate->current_selling_rate, 'weighted_cost_rate' => 0, 'revenue' => 0, 'inventory_cost' => 0, 'gross_profit' => 0]);
            $rejected = $pending->replicate(['sale_number']);
            $rejected->sale_number = app(TransactionNumberGenerator::class)->next('sale');
            $rejected->status = 'rejected';
            $rejected->approval_status = 'rejected';
            $rejected->rejection_reason = 'Demo rejection';
            $rejected->save();
        }
        Setting::query()->create(['key' => 'demo_seeded_at', 'value' => now()->toIso8601String()]);
    }
}
