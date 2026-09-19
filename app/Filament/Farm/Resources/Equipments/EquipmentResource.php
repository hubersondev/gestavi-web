<?php

namespace App\Filament\Farm\Resources\Equipments;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Equipments\Pages\CreateEquipment;
use App\Filament\Farm\Resources\Equipments\Pages\EditEquipment;
use App\Filament\Farm\Resources\Equipments\Pages\ListEquipments;
use App\Filament\Farm\Resources\Equipments\Pages\ViewEquipment;
use App\Filament\Farm\Resources\Equipments\RelationManagers\MaintenancesRelationManager;
use App\Filament\Farm\Resources\Equipments\RelationManagers\MovementsRelationManager;
use App\Filament\Farm\Resources\Equipments\Schemas\EquipmentForm;
use App\Filament\Farm\Resources\Equipments\Schemas\EquipmentInfolist;
use App\Filament\Farm\Resources\Equipments\Tables\EquipmentsTable;
use App\Models\Equipment;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EquipmentResource extends Resource
{
    use RequiresPlanFeatures;
    use ChecksOrganisationPermissions;
    use ScopesToMemberFarms;

    protected static ?string $model = Equipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Équipements';

    protected static ?string $modelLabel = 'Équipement';

    protected static ?string $pluralModelLabel = 'Équipements';

    protected static ?int $navigationSort = 5;

    protected static string $permissionGroup = 'equipments';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Equipment];
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EquipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MaintenancesRelationManager::class,
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipments::route('/'),
            'create' => CreateEquipment::route('/create'),
            'view' => ViewEquipment::route('/{record}'),
            'edit' => EditEquipment::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Equipment est lié indirectement via farm → organisation (pas de FK directe).
        // Le farm_id du formulaire établit l'appartenance au tenant.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $membership = static::currentMembership();
        $currentFarm = static::currentFarm();

        // Utiliser farm_id directement pour inclure les équipements en stock (building_id nullable)
        return $query->whereHas('farm', function (Builder $q) use ($tenant, $membership, $currentFarm) {
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
