<?php

namespace App\Filament\Farm\Resources\EquipmentMaintenances;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\EquipmentMaintenances\Pages\CreateEquipmentMaintenance;
use App\Filament\Farm\Resources\EquipmentMaintenances\Pages\EditEquipmentMaintenance;
use App\Filament\Farm\Resources\EquipmentMaintenances\Pages\ListEquipmentMaintenances;
use App\Filament\Farm\Resources\EquipmentMaintenances\Pages\ViewEquipmentMaintenance;
use App\Filament\Farm\Resources\EquipmentMaintenances\Schemas\EquipmentMaintenanceForm;
use App\Filament\Farm\Resources\EquipmentMaintenances\Schemas\EquipmentMaintenanceInfolist;
use App\Filament\Farm\Resources\EquipmentMaintenances\Tables\EquipmentMaintenancesTable;
use App\Models\EquipmentMaintenance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EquipmentMaintenanceResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = EquipmentMaintenance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Équipements';

    protected static ?string $modelLabel = 'Maintenance';

    protected static ?string $pluralModelLabel = 'Maintenances';

    protected static ?int $navigationSort = 6;

    protected static string $permissionGroup = 'equipments';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::EquipmentMaintenance];
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentMaintenanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EquipmentMaintenanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentMaintenancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipmentMaintenances::route('/'),
            'create' => CreateEquipmentMaintenance::route('/create'),
            'view' => ViewEquipmentMaintenance::route('/{record}'),
            'edit' => EditEquipmentMaintenance::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $currentFarm = static::currentFarm();

        return $query->whereHas('equipment', function (Builder $q) use ($tenant, $currentFarm): void {
            if ($currentFarm) {
                $q->where('farm_id', $currentFarm->id);
            } else {
                $q->whereHas('building', fn (Builder $b) => $b->whereHas('farm', fn (Builder $f) => $f->where('organisation_id', $tenant?->getKey())));
            }
        });
    }
}
