<?php

namespace App\Filament\Farm\Resources\Vaccinations;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Vaccinations\Pages\CreateVaccination;
use App\Filament\Farm\Resources\Vaccinations\Pages\EditVaccination;
use App\Filament\Farm\Resources\Vaccinations\Pages\ListVaccinations;
use App\Filament\Farm\Resources\Vaccinations\Pages\ViewVaccination;
use App\Filament\Farm\Resources\Vaccinations\Schemas\VaccinationForm;
use App\Filament\Farm\Resources\Vaccinations\Schemas\VaccinationInfolist;
use App\Filament\Farm\Resources\Vaccinations\Tables\VaccinationsTable;
use App\Models\Vaccination;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VaccinationResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Vaccination::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?string $navigationLabel = 'Vaccinations';

    protected static ?string $modelLabel = 'Vaccination';

    protected static ?string $pluralModelLabel = 'Vaccinations';

    protected static ?string $recordTitleAttribute = 'treatment_name';

    protected static ?int $navigationSort = 6;

    protected static string $permissionGroup = 'vaccinations';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Vaccinations];
    }

    public static function form(Schema $schema): Schema
    {
        return VaccinationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VaccinationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VaccinationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVaccinations::route('/'),
            'create' => CreateVaccination::route('/create'),
            'view' => ViewVaccination::route('/{record}'),
            'edit' => EditVaccination::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Vaccination est lié au tenant indirectement via flock → building → farm → organisation.
        // Il n'a pas de FK directe sur organisation_id ; le flock_id établit l'appartenance au tenant.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
