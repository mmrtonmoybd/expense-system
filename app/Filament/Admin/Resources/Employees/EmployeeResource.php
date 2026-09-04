<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Employees;

use App\Filament\Admin\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Admin\Resources\Employees\Pages\EditEmployee;
use App\Filament\Admin\Resources\Employees\Pages\ListEmployees;
use App\Filament\Admin\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Admin\Resources\Employees\RelationManagers\PayrollsRelationManager;
use App\Filament\Admin\Resources\Employees\RelationManagers\SalaryHistoriesRelationManager;
use App\Filament\Admin\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Admin\Resources\Employees\Schemas\EmployeeInfolist;
use App\Filament\Admin\Resources\Employees\Tables\EmployeesTable;
use App\Filament\Admin\Resources\Employees\Widgets\EmployeePayrollChart;
use App\Filament\Admin\Resources\Employees\Widgets\EmployeePayrollStats;
use App\Filament\Admin\Resources\Employees\Widgets\EmployeeStatsOverview;
use App\Models\Employee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Identification;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('panel.resources.employee.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.employee.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.groups.Payroll');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['employee_code', 'name', 'phone', 'email', 'designation'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Code' => $record->employee_code, 'Phone' => $record->phone, 'Designation' => $record->designation];
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SalaryHistoriesRelationManager::class,
            PayrollsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            EmployeePayrollStats::class,
            EmployeePayrollChart::class,
            EmployeeStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'view' => ViewEmployee::route('/{record}'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
