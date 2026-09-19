<?php

namespace App\Filament\Farm\Resources\Farms;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Farms\Pages\CreateFarm;
use App\Filament\Farm\Resources\Farms\Pages\EditFarm;
use App\Filament\Farm\Resources\Farms\Pages\ListFarms;
use App\Filament\Farm\Resources\Farms\Pages\ViewFarm;
use App\Filament\Farm\Resources\Farms\Schemas\FarmForm;
use App\Filament\Farm\Resources\Farms\Schemas\FarmInfolist;
use App\Filament\Farm\Resources\Farms\Tables\FarmsTable;
use App\Models\Farm;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FarmResource extends Resource
{
    use RequiresPlanFeatures;
    use ChecksOrganisationPermissions;
    use ScopesToMemberFarms;

    protected static ?string $model = Farm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static string | UnitEnum | null $navigationGroup = 'Exploitation';
    protected static ?string $modelLabel = 'Ferme';

    protected static ?string $pluralModelLabel = 'Fermes';

    protected static ?int $navigationSort = 2;

    protected static string $permissionGroup = 'farms';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::MultiFarms];
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! parent::shouldRegisterNavigation()) {
            return false;
        }

        return static::currentFarm() === null;
    }

    public static function form(Schema $schema): Schema
    {
        return FarmForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FarmInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FarmsTable::configure($table);
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
            'index' => ListFarms::route('/'),
            'create' => CreateFarm::route('/create'),
            'view' => ViewFarm::route('/{record}'),
            'edit' => EditFarm::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('organisation_id', Filament::getTenant()?->id);

        $membership = static::currentMembership();

        if ($membership) {
            $query->accessibleBy($membership);
        }

        return $query;
    }
}
