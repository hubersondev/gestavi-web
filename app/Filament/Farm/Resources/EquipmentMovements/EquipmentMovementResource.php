<?php

namespace App\Filament\Farm\Resources\EquipmentMovements;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\EquipmentMovements\Pages\CreateEquipmentMovement;
use App\Filament\Farm\Resources\EquipmentMovements\Pages\EditEquipmentMovement;
use App\Filament\Farm\Resources\EquipmentMovements\Pages\ListEquipmentMovements;
use App\Filament\Farm\Resources\EquipmentMovements\Pages\ViewEquipmentMovement;
use App\Filament\Farm\Resources\EquipmentMovements\Schemas\EquipmentMovementForm;
use App\Filament\Farm\Resources\EquipmentMovements\Schemas\EquipmentMovementInfolist;
use App\Filament\Farm\Resources\EquipmentMovements\Tables\EquipmentMovementsTable;
use App\Models\EquipmentMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EquipmentMovementResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = EquipmentMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Équipements';

    protected static ?string $modelLabel = 'Déplacement';

    protected static ?string $pluralModelLabel = 'Déplacements';

    protected static ?int $navigationSort = 7;

    protected static string $permissionGroup = 'equipments';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Equipment];
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentMovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EquipmentMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipmentMovements::route('/'),
            'create' => CreateEquipmentMovement::route('/create'),
            'view' => ViewEquipmentMovement::route('/{record}'),
            'edit' => EditEquipmentMovement::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $currentFarm = static::currentFarm();

        return $query->whereHas('equipment', function (Builder $q) use ($tenant, $currentFarm): void {
            if ($currentFarm) {
                $q->where('farm_id', $currentFarm->id);
            } else {
                $q->whereHas('building.farm', fn (Builder $f) => $f->where('organisation_id', $tenant?->getKey()));
            }
        });
    }
}
