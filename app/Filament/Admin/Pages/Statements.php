<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Customer;
use App\Models\Employee;
use App\Services\ReportDataBuilder;
use App\Support\ReportVisibility;
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

/**
 * Party-level statements: everything charged to, and settled by, one customer or employee
 * over a period, in date order with a running balance.
 */
final class Statements extends Page
{
    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $report = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.admin.pages.statements';

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.statements');
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
        return __('panel.pages.statements');
    }

    public function mount(ReportDataBuilder $builder): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill([
            'report' => array_key_first(self::statementOptions()),
            'from' => now()->startOfYear()->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $this->buildReport($builder);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Statement')
                    ->description('Pick who the statement is for and the period it should cover.')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                            Select::make('report')
                                ->label('Statement type')
                                ->options(self::statementOptions())
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set): mixed => $set('customer_id', null) ?? $set('employee_id', null)),
                            DatePicker::make('from')->label('From')->required()->native(false)->maxDate(fn (Get $get): mixed => $get('to')),
                            DatePicker::make('to')->label('To')->required()->native(false)->minDate(fn (Get $get): mixed => $get('from')),
                            Select::make('customer_id')
                                ->label('Customer')
                                ->searchable()
                                ->required()
                                ->visible(fn (Get $get): bool => $get('report') === 'customer_statement')
                                ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                                    ->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"))
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->get(['id', 'name', 'phone'])
                                    ->mapWithKeys(fn (Customer $customer): array => [$customer->id => "{$customer->name} ({$customer->phone})"])
                                    ->all())
                                ->getOptionLabelUsing(fn (mixed $value): ?string => Customer::query()->whereKey($value)->value('name')),
                            Select::make('employee_id')
                                ->label('Employee')
                                ->searchable()
                                ->required()
                                ->visible(fn (Get $get): bool => $get('report') === 'employee_statement')
                                ->getSearchResultsUsing(fn (string $search): array => Employee::query()
                                    ->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%"))
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->get(['id', 'name', 'employee_code'])
                                    ->mapWithKeys(fn (Employee $employee): array => [$employee->id => "{$employee->name} ({$employee->employee_code})"])
                                    ->all())
                                ->getOptionLabelUsing(fn (mixed $value): ?string => Employee::query()->whereKey($value)->value('name')),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function apply(ReportDataBuilder $builder): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->getState();

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
                ->label('Build statement')
                ->submit('apply')
                ->icon(Heroicon::DocumentMagnifyingGlass),
        ];
    }

    /** @return array<string, string> */
    private static function statementOptions(): array
    {
        $visibility = ReportVisibility::for(auth()->user());

        return collect([
            'customer_statement' => 'Customer statement',
            'employee_statement' => 'Employee salary statement',
        ])
            ->filter(fn (string $label, string $type): bool => $visibility->allowsReport($type))
            ->all();
    }

    private function buildReport(ReportDataBuilder $builder): void
    {
        abort_unless(self::canAccess(), 403);

        // Raw state, so the page can show the empty statement and its hint before a party
        // has been picked. The submit action validates before it gets here.
        $this->report = $builder->build(
            (array) $this->form->getRawState(),
            visibility: ReportVisibility::for(auth()->user()),
        );
    }
}
