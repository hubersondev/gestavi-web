<?php

namespace App\Filament\Farm\Resources\Payrolls;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Payrolls\Pages\CreatePayroll;
use App\Filament\Farm\Resources\Payrolls\Pages\EditPayroll;
use App\Filament\Farm\Resources\Payrolls\Pages\ListPayrolls;
use App\Filament\Farm\Resources\Payrolls\Pages\ViewPayroll;
use App\Filament\Farm\Resources\Payrolls\Schemas\PayrollForm;
use App\Filament\Farm\Resources\Payrolls\Schemas\PayrollInfolist;
use App\Filament\Farm\Resources\Payrolls\Tables\PayrollsTable;
use App\Models\Payroll;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PayrollResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Payroll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Ressources Humaines';

    protected static ?string $navigationLabel = 'Bulletins de paie';

    protected static ?string $modelLabel = 'Bulletin de paie';

    protected static ?string $pluralModelLabel = 'Bulletins de paie';

    protected static ?int $navigationSort = 35;

    protected static string $permissionGroup = 'payrolls';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Payroll];
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

    public static function getRelations(): array
    {
        return [];
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

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Payroll lié via employee → organisation — pas d'auto-association Filament
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
