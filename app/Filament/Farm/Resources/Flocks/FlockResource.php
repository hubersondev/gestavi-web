<?php

namespace App\Filament\Farm\Resources\Flocks;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Flocks\Pages\CreateFlock;
use App\Filament\Farm\Resources\Flocks\Pages\EditFlock;
use App\Filament\Farm\Resources\Flocks\Pages\ListFlocks;
use App\Filament\Farm\Resources\Flocks\Pages\ViewFlock;
use App\Filament\Farm\Resources\Flocks\Schemas\FlockForm;
use App\Filament\Farm\Resources\Flocks\Schemas\FlockInfolist;
use App\Filament\Farm\Resources\Flocks\Tables\FlocksTable;
use App\Models\Flock;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FlockResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Flock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?string $modelLabel = 'Bande';

    protected static ?string $pluralModelLabel = 'Bandes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    protected static string $permissionGroup = 'flocks';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Flocks];
    }

    public static function form(Schema $schema): Schema
    {
        return FlockForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FlockInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlocksTable::configure($table);
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
            'index' => ListFlocks::route('/'),
            'create' => CreateFlock::route('/create'),
            'view' => ViewFlock::route('/{record}'),
            'edit' => EditFlock::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Flock est lié au tenant indirectement via building → farm → organisation.
        // Il n'a pas de FK directe sur organisation_id ; le building_id du formulaire
        // établit l'appartenance au tenant. On désactive l'association automatique
        // de Filament pour éviter l'appel à $record->organisation() inexistant.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
