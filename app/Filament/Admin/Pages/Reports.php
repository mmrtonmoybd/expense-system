<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Platform;
use App\Models\User;
use App\Services\ReportDataBuilder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class Reports extends Page
{
    private const int RowsPerPage = 50;

    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $report = [];

    public int $reportPage = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.admin.pages.reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:Reports') ?? false;
    }

    public function mount(ReportDataBuilder $builder): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill([
            'report' => 'sales',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
        $this->buildReport($builder);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Report filters')
                    ->description('Choose a report first. Only filters supported by that report are shown.')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])
                            ->schema([
                                Select::make('report')
                                    ->label('Report')
                                    ->options([
                                        'sales' => 'USD Sales',
                                        'purchases' => 'USD Purchases',
                                        'inventory' => 'Inventory Ledger / Valuation',
                                        'platforms' => 'Platform Performance',
                                        'customers' => 'Customer Performance',
                                        'expenses' => 'Expenses',
                                        'payroll' => 'Payroll',
                                        'pnl' => 'Profit & Loss',
                                        'audit' => 'Audit Log',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        foreach (['customer_id', 'platform_id', 'expense_category_id', 'employee_id', 'user_id', 'status'] as $field) {
                                            $set($field, null);
                                        }
                                    }),
                                DatePicker::make('from')
                                    ->label('From')
                                    ->required()
                                    ->native(false),
                                DatePicker::make('to')
                                    ->label('To')
                                    ->required()
                                    ->afterOrEqual('from')
                                    ->native(false),
                                $this->customerSelect(),
                                $this->platformSelect(),
                                $this->expenseCategorySelect(),
                                $this->employeeSelect(),
                                $this->userSelect(),
                                Select::make('status')
                                    ->label(fn (Get $get): string => $get('report') === 'inventory' ? 'Transaction type' : 'Status')
                                    ->options(fn (Get $get): array => $this->statusOptions((string) $get('report')))
                                    ->visible(fn (Get $get): bool => in_array($get('report'), ['sales', 'purchases', 'inventory', 'payroll'], true)),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function apply(ReportDataBuilder $builder): void
    {
        abort_unless(self::canAccess(), 403);

        $this->reportPage = 1;
        $this->buildReport($builder);
    }

    public function previousReportPage(ReportDataBuilder $builder): void
    {
        $this->reportPage = max(1, $this->reportPage - 1);
        $this->buildReport($builder);
    }

    public function nextReportPage(ReportDataBuilder $builder): void
    {
        $lastPage = (int) data_get($this->report, 'pagination.last_page', 1);
        $this->reportPage = min($lastPage, $this->reportPage + 1);
        $this->buildReport($builder);
    }

    public function exportUrl(string $format): string
    {
        return route('reports.export', [
            'format' => $format,
            ...array_filter($this->data ?? [], fn (mixed $value): bool => filled($value)),
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('apply')
                ->label('Apply filters')
                ->submit('apply')
                ->icon(Heroicon::Funnel),
        ];
    }

    private function buildReport(ReportDataBuilder $builder): void
    {
        abort_unless(self::canAccess(), 403);

        $this->report = $builder->build(
            $this->form->getState(),
            self::RowsPerPage,
            $this->reportPage,
        );
    }

    private function customerSelect(): Select
    {
        return Select::make('customer_id')
            ->label('Customer')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                ->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'phone'])
                ->mapWithKeys(fn (Customer $customer): array => [$customer->id => "{$customer->name} ({$customer->phone})"])
                ->all())
            ->getOptionLabelUsing(function (mixed $value): ?string {
                $customer = Customer::query()->find($value, ['id', 'name', 'phone']);

                return $customer ? "{$customer->name} ({$customer->phone})" : null;
            })
            ->visible(fn (Get $get): bool => in_array($get('report'), ['sales', 'platforms', 'customers'], true));
    }

    private function platformSelect(): Select
    {
        return Select::make('platform_id')
            ->label('Platform')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Platform::query()
                ->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('short_name', 'like', "%{$search}%");
                })
                ->orderBy('sort_order')
                ->limit(50)
                ->pluck('name', 'id')
                ->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => Platform::query()->whereKey($value)->value('name'))
            ->visible(fn (Get $get): bool => in_array($get('report'), ['sales', 'platforms', 'customers'], true));
    }

    private function expenseCategorySelect(): Select
    {
        return Select::make('expense_category_id')
            ->label('Expense category')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => ExpenseCategory::query()
                ->where('name', 'like', "%{$search}%")
                ->orderBy('sort_order')
                ->limit(50)
                ->pluck('name', 'id')
                ->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => ExpenseCategory::query()->whereKey($value)->value('name'))
            ->visible(fn (Get $get): bool => $get('report') === 'expenses');
    }

    private function employeeSelect(): Select
    {
        return Select::make('employee_id')
            ->label('Employee')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Employee::query()
                ->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'employee_code'])
                ->mapWithKeys(fn (Employee $employee): array => [$employee->id => "{$employee->name} ({$employee->employee_code})"])
                ->all())
            ->getOptionLabelUsing(function (mixed $value): ?string {
                $employee = Employee::query()->find($value, ['id', 'name', 'employee_code']);

                return $employee ? "{$employee->name} ({$employee->employee_code})" : null;
            })
            ->visible(fn (Get $get): bool => $get('report') === 'payroll');
    }

    private function userSelect(): Select
    {
        return Select::make('user_id')
            ->label('User')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => User::query()
                ->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->limit(50)
                ->pluck('name', 'id')
                ->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->whereKey($value)->value('name'))
            ->visible(fn (Get $get): bool => $get('report') === 'audit');
    }

    /** @return array<string, string> */
    private function statusOptions(string $report): array
    {
        return match ($report) {
            'purchases' => ['draft' => 'Draft', 'posted' => 'Posted', 'reversed' => 'Reversed'],
            'inventory' => [
                'opening_balance' => 'Opening balance',
                'purchase' => 'Purchase',
                'sale' => 'Sale',
                'positive_adjustment' => 'Positive adjustment',
                'negative_adjustment' => 'Negative adjustment',
                'reversal' => 'Reversal',
            ],
            'payroll' => ['draft' => 'Draft', 'unpaid' => 'Unpaid', 'partially_paid' => 'Partially paid', 'paid' => 'Paid'],
            default => ['draft' => 'Draft', 'pending' => 'Pending', 'posted' => 'Posted', 'rejected' => 'Rejected', 'reversed' => 'Reversed'],
        };
    }
}
