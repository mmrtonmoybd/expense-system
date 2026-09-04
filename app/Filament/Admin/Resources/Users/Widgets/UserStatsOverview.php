<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Widgets;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Widgets\Concerns\SummarisesTable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

final class UserStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable, SummarisesTable;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getTablePage(): string
    {
        return ListUsers::class;
    }

    protected function getStats(): array
    {
        $ids = $this->summaryQuery()->select('users.id');
        $counts = $this->summarise([
            'users' => 'COUNT(*)',
            'verified' => 'SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END)',
        ]);

        $roles = DB::table('model_has_roles')->whereIn('model_id', $ids)->distinct()->count('role_id');
        $roleless = (int) $counts['users'] - DB::table('model_has_roles')->whereIn('model_id', $ids)->distinct()->count('model_id');
        $recent = DB::table('audit_logs')->whereIn('user_id', $ids)->where('occurred_at', '>=', now()->subDays(30))->count();

        return [
            $this->summaryStat('Users in view', $this->quantity($counts['users'], 0), Heroicon::Users, 'gray', "{$counts['verified']} verified"),
            $this->summaryStat('Distinct roles', $this->quantity($roles, 0), Heroicon::ShieldCheck, 'info'),
            $this->summaryStat('Without a role', $this->quantity($roleless, 0), Heroicon::ExclamationTriangle, $roleless > 0 ? 'danger' : 'success'),
            $this->summaryStat('Audited actions, 30 days', $this->quantity($recent, 0), Heroicon::ClipboardDocumentList, 'primary'),
        ];
    }
}
