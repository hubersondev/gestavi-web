<?php

namespace App\Filament\Farm\Resources\Employees;

use App\Enums\PlanFeature;
use App\Filament\Audit\AuditTrailRelationManager;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Farm\Resources\Employees\Pages\EditEmployee;
use App\Filament\Farm\Resources\Employees\Pages\ListEmployees;
use App\Filament\Farm\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Farm\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Farm\Resources\Employees\Schemas\EmployeeInfolist;
use App\Filament\Farm\Resources\Employees\Tables\EmployeesTable;
use App\Models\Employee;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmployeeResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Ressources Humaines';

    protected static ?string $navigationLabel = 'Employés';

    protected static ?string $modelLabel = 'Employé';

    protected static ?string $pluralModelLabel = 'Employés';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?int $navigationSort = 30;

    protected static string $permissionGroup = 'employees';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Employees];
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
        return [AuditTrailRelationManager::class];
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

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Employee a organisation_id direct — pas d'auto-association Filament nécessaire
        // car on le set manuellement dans handleRecordCreation
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope(
            $query
                ->with('activeContract')
                ->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
