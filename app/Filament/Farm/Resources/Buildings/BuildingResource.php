<?php

namespace App\Filament\Farm\Resources\Buildings;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Buildings\Pages\CreateBuilding;
use App\Filament\Farm\Resources\Buildings\Pages\EditBuilding;
use App\Filament\Farm\Resources\Buildings\Pages\ListBuildings;
use App\Filament\Farm\Resources\Buildings\Pages\ViewBuilding;
use App\Filament\Farm\Resources\Buildings\Schemas\BuildingForm;
use App\Filament\Farm\Resources\Buildings\Schemas\BuildingInfolist;
use App\Filament\Farm\Resources\Buildings\Tables\BuildingsTable;
use App\Models\Building;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BuildingResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Building::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Exploitation';

    protected static ?string $modelLabel = 'Bâtiment';

    protected static ?string $pluralModelLabel = 'Bâtiments';

    protected static ?int $navigationSort = 3;

    protected static string $permissionGroup = 'buildings';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Buildings];
    }

    public static function form(Schema $schema): Schema
    {
        return BuildingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BuildingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BuildingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuildings::route('/'),
            'create' => CreateBuilding::route('/create'),
            'view' => ViewBuilding::route('/{record}'),
            'edit' => EditBuilding::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Building est lié indirectement via farm → organisation (pas de FK directe).
        // Le farm_id du formulaire établit l'appartenance au tenant.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
