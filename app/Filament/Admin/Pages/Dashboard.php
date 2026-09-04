<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\CustomerPerformanceChart;
use App\Filament\Admin\Widgets\ExpenseBreakdownChart;
use App\Filament\Admin\Widgets\FinanceStats;
use App\Filament\Admin\Widgets\InventoryTrendChart;
use App\Filament\Admin\Widgets\MonthlyPerformanceChart;
use App\Filament\Admin\Widgets\PayrollHealthChart;
use App\Filament\Admin\Widgets\PlatformPerformanceChart;
use App\Filament\Admin\Widgets\RevenueTrendChart;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class Dashboard extends BaseDashboard
{
    protected static string|null|UnitEnum $navigationGroup = 'General';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    protected static ?int $navigationSort = -2;

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.dashboard');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.General');
    }

    public function getTitle(): string
    {
        return __('panel.pages.dashboard');
    }

    public function getColumns(): int|array
    {
        return ['md' => 2];
    }

    /**
     * Listed explicitly so the analytics widgets, which are driven by the Business Analytics
     * filter bar, do not also appear here without one.
     */
    public function getWidgets(): array
    {
        return [
            FinanceStats::class,
            RevenueTrendChart::class,
            InventoryTrendChart::class,
            PlatformPerformanceChart::class,
            CustomerPerformanceChart::class,
            ExpenseBreakdownChart::class,
            PayrollHealthChart::class,
            MonthlyPerformanceChart::class,
        ];
    }
}
