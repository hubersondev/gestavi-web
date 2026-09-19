<?php

namespace App\Filament\Farm\Resources\HealthIncidents;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\HealthIncidents\Pages\CreateHealthIncident;
use App\Filament\Farm\Resources\HealthIncidents\Pages\EditHealthIncident;
use App\Filament\Farm\Resources\HealthIncidents\Pages\ListHealthIncidents;
use App\Filament\Farm\Resources\HealthIncidents\Pages\ViewHealthIncident;
use App\Filament\Farm\Resources\HealthIncidents\Schemas\HealthIncidentForm;
use App\Filament\Farm\Resources\HealthIncidents\Schemas\HealthIncidentInfolist;
use App\Filament\Farm\Resources\HealthIncidents\Tables\HealthIncidentsTable;
use App\Models\HealthIncident;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class HealthIncidentResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = HealthIncident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?string $navigationLabel = 'Incidents sanitaires';

    protected static ?string $modelLabel = 'Incident sanitaire';

    protected static ?string $pluralModelLabel = 'Incidents sanitaires';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 7;

    protected static string $permissionGroup = 'health_incidents';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::HealthIncidents];
    }

    public static function form(Schema $schema): Schema
    {
        return HealthIncidentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HealthIncidentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HealthIncidentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHealthIncidents::route('/'),
            'create' => CreateHealthIncident::route('/create'),
            'view' => ViewHealthIncident::route('/{record}'),
            'edit' => EditHealthIncident::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // HealthIncident est lié au tenant indirectement via flock → building → farm → organisation.
        // Il n'a pas de FK directe sur organisation_id ; le flock_id établit l'appartenance au tenant.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $membership = static::currentMembership();
        $currentFarm = static::currentFarm();

        return $query->whereHas('flock.building.farm', function (Builder $q) use ($tenant, $membership, $currentFarm) {
            $q->where('farms.organisation_id', $tenant?->getKey());

            if ($membership) {
                $q->accessibleBy($membership);
            }

            if ($currentFarm) {
                $q->where('farms.id', $currentFarm->id);
            }
        });
    }
}
