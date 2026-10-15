<?php

namespace App\Filament\Farm\Resources\VetVisits;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\VetVisits\Pages\CreateVetVisit;
use App\Filament\Farm\Resources\VetVisits\Pages\EditVetVisit;
use App\Filament\Farm\Resources\VetVisits\Pages\ListVetVisits;
use App\Filament\Farm\Resources\VetVisits\Pages\ViewVetVisit;
use App\Filament\Farm\Resources\VetVisits\Schemas\VetVisitForm;
use App\Filament\Farm\Resources\VetVisits\Schemas\VetVisitInfolist;
use App\Filament\Farm\Resources\VetVisits\Tables\VetVisitsTable;
use App\Models\VetVisit;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VetVisitResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = VetVisit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?string $navigationLabel = 'Visites vétérinaires';

    protected static ?string $modelLabel = 'Visite vétérinaire';

    protected static ?string $pluralModelLabel = 'Visites vétérinaires';

    protected static ?string $recordTitleAttribute = 'reason';

    protected static ?int $navigationSort = 4;

    protected static string $permissionGroup = 'vet_visits';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::VetVisits];
    }

    public static function form(Schema $schema): Schema
    {
        return VetVisitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VetVisitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VetVisitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVetVisits::route('/'),
            'create' => CreateVetVisit::route('/create'),
            'view' => ViewVetVisit::route('/{record}'),
            'edit' => EditVetVisit::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // VetVisit est lié au tenant indirectement via farm → organisation (pas de FK directe).
        // Le farm_id du formulaire établit l'appartenance ; on désactive l'auto-association de Filament.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
