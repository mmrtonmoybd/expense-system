<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Analytics\AnalyticsSummaryStats;
use App\Filament\Admin\Widgets\Analytics\CustomerConcentrationChart;
use App\Filament\Admin\Widgets\Analytics\InventoryPositionChart;
use App\Filament\Admin\Widgets\Analytics\OverheadCompositionChart;
use App\Filament\Admin\Widgets\Analytics\PayrollCoverageChart;
use App\Filament\Admin\Widgets\Analytics\PlatformMixChart;
use App\Filament\Admin\Widgets\Analytics\ProfitCompositionChart;
use App\Filament\Admin\Widgets\Analytics\RateSpreadChart;
use App\Models\Customer;
use App\Models\Platform;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * One filter bar driving every chart, so the whole business can be read for a single window,
 * customer or platform without touching each widget.
 */
final class BusinessAnalytics extends Page
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartPie;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Business Analytics';

    protected string $view = 'filament.admin.pages.business-analytics';

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.business_analytics');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Reports');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:Reports') ?? false;
    }

    public function getTitle(): string
    {
        return __('panel.pages.business_analytics');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Analysis window')
                ->description('Every chart below reads this window. Narrow it to a customer or a platform to see their slice.')
                ->compact()
                ->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 5])
                ->schema([
                    DatePicker::make('from')->label('From')->default(today()->startOfMonth()->subMonths(5))->native(false)->maxDate(fn (callable $get): mixed => $get('to')),
                    DatePicker::make('to')->label('To')->default(today())->native(false)->minDate(fn (callable $get): mixed => $get('from')),
                    Select::make('granularity')
                        ->label('Group by')
                        ->options(['day' => 'Day', 'week' => 'Week', 'month' => 'Month'])
                        ->default('month')
                        ->selectablePlaceholder(false),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->placeholder('All customers')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                            ->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => Customer::query()->whereKey($value)->value('name')),
                    Select::make('platform_id')
                        ->label('Platform')
                        ->placeholder('All platforms')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Platform::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orderBy('sort_order')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => Platform::query()->whereKey($value)->value('name')),
                ]),
        ]);
    }

    /**
     * Footer widgets, so the page renders the filter bar first: the page component lays out
     * header widgets, then the view, then footer widgets.
     */
    public function getFooterWidgets(): array
    {
        return [
            AnalyticsSummaryStats::class,
            ProfitCompositionChart::class,
            PlatformMixChart::class,
            CustomerConcentrationChart::class,
            OverheadCompositionChart::class,
            RateSpreadChart::class,
            InventoryPositionChart::class,
            PayrollCoverageChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return ['default' => 1, 'md' => 2];
    }
}
