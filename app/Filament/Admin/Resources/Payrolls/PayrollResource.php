<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payrolls;

use App\Filament\Admin\Resources\Payrolls\Pages\CreatePayroll;
use App\Filament\Admin\Resources\Payrolls\Pages\EditPayroll;
use App\Filament\Admin\Resources\Payrolls\Pages\ListPayrolls;
use App\Filament\Admin\Resources\Payrolls\Pages\ViewPayroll;
use App\Filament\Admin\Resources\Payrolls\RelationManagers\PaymentsRelationManager;
use App\Filament\Admin\Resources\Payrolls\Schemas\PayrollForm;
use App\Filament\Admin\Resources\Payrolls\Schemas\PayrollInfolist;
use App\Filament\Admin\Resources\Payrolls\Tables\PayrollsTable;
use App\Models\Payroll;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'payroll_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['payroll_number', 'employee.name', 'employee.phone', 'employee.employee_code'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('employee:id,employee_code,name,phone');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Employee' => $record->employee->name, 'Month' => $record->payroll_month->format('M Y'), 'Status' => str($record->status)->headline()->toString()];
    }

    public static function form(Schema $schema): Schema
    {
        return PayrollForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayrollInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollsTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && $record instanceof Payroll && ! $record->payments()->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && $record instanceof Payroll && ! $record->payments()->exists();
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrolls::route('/'),
            'create' => CreatePayroll::route('/create'),
            'view' => ViewPayroll::route('/{record}'),
            'edit' => EditPayroll::route('/{record}/edit'),
        ];
    }
}
