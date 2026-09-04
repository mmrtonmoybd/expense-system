<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryState;
use App\Models\Setting;
use App\Models\User;
use App\Services\UsdSellRateService;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes the business data the demo seeder produced and returns the ledger to zero.
 *
 * The seeder does not tag the rows it writes, so there is no way to tell a demo sale from a
 * real one. This command therefore clears every transactional and master-data table rather
 * than pretending to be selective, and says so before it does anything. Roles, permissions
 * and the branding in Settings are left alone, so the panel still works afterwards.
 */
final class CleanDemoData extends Command
{
    use ConfirmableTrait;

    /**
     * Child rows first, so a wrong order fails loudly instead of leaving orphans behind.
     *
     * @var array<int, string>
     */
    private const array Tables = [
        'sale_cost_allocations',
        'invoices',
        'sale_lines',
        'sales',
        'payroll_payments',
        'payrolls',
        'employee_salary_histories',
        'employees',
        'expenses',
        'expense_categories',
        'inventory_transactions',
        'costing_method_changes',
        'inventory_layers',
        'usd_purchases',
        'customer_platform_rate_histories',
        'customer_platforms',
        'customers',
        'platforms',
        'usd_sell_rates',
        'audit_logs',
        'notifications',
        'transaction_sequences',
    ];

    /** Operational state that only makes sense alongside the data being removed. */
    private const array Settings = [
        'demo_seeded_at',
        UsdSellRateService::SettingKey,
    ];

    private const string DemoEmailSuffix = '@demo.test';

    private const string Confirmation = 'Delete all business data and reset the inventory ledger to zero?';

    protected $signature = 'demo:clean
        {--force : Skip the confirmation prompt}
        {--keep-users : Leave the demo login accounts in place}';

    protected $description = 'Delete all demo business data and reset the inventory ledger';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $counts = $this->counts();
        $total = array_sum($counts);

        if ($total === 0 && ! $this->hasDemoUsers()) {
            $this->components->info('Nothing to clean: there is no business data in the database.');

            return self::SUCCESS;
        }

        $this->components->warn("This deletes ALL business data, not only the rows the demo seeder created: {$this->format($total)} rows across ".count(array_filter($counts)).' tables.');
        $this->table(
            ['Table', 'Rows'],
            collect($counts)->filter()->map(fn (int $rows, string $table): array => [$table, number_format($rows)])->values()->all(),
        );

        if (! $this->option('force') && ! $this->confirm(self::Confirmation)) {
            $this->components->info('Nothing was deleted.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            foreach (self::Tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            $this->resetInventoryLedger();

            Setting::query()->whereIn('key', self::Settings)->delete();

            $this->deleteDemoUsers();
        });

        $this->components->info("Removed {$this->format($total)} rows. The inventory ledger is back to zero.");

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return collect(self::Tables)
            ->filter(fn (string $table): bool => Schema::hasTable($table))
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }

    /**
     * The position row is a singleton with a unique index, so it is zeroed rather than
     * deleted; recreating it is the migration's job, not this command's.
     */
    private function resetInventoryLedger(): void
    {
        InventoryState::query()->update([
            'usd_balance' => '0',
            'inventory_value_bdt' => '0',
            'weighted_average_cost' => '0',
        ]);
    }

    private function hasDemoUsers(): bool
    {
        return $this->demoUsers()->exists();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<User> */
    private function demoUsers()
    {
        return User::query()->where('email', 'like', '%'.self::DemoEmailSuffix);
    }

    private function deleteDemoUsers(): void
    {
        if ($this->option('keep-users')) {
            return;
        }

        $demo = $this->demoUsers()->count();

        if ($demo === 0) {
            return;
        }

        // Removing every login would lock the panel, so the accounts stay if they are all
        // that is left. Roles and permissions are never touched either way.
        if ($demo >= User::query()->count()) {
            $this->components->warn('Demo login accounts were kept: deleting them would leave the panel with no users.');

            return;
        }

        $this->demoUsers()->each(fn (User $user): ?bool => $user->delete());
        $this->components->info("Removed {$demo} demo login accounts.");
    }

    private function format(int $count): string
    {
        return number_format($count);
    }
}
