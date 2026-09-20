<?php

namespace App\Filament\Farm\Resources\Contracts;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Contracts\Pages\CreateContract;
use App\Filament\Farm\Resources\Contracts\Pages\EditContract;
use App\Filament\Farm\Resources\Contracts\Pages\ListContracts;
use App\Filament\Farm\Resources\Contracts\Pages\ViewContract;
use App\Filament\Farm\Resources\Contracts\Schemas\ContractForm;
use App\Filament\Farm\Resources\Contracts\Schemas\ContractInfolist;
use App\Filament\Farm\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ContractResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Ressources Humaines';

    protected static ?string $navigationLabel = 'Contrats';

    protected static ?string $modelLabel = 'Contrat';

    protected static ?string $pluralModelLabel = 'Contrats';

    protected static ?int $navigationSort = 31;

    protected static string $permissionGroup = 'contracts';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Contracts];
    }

    public static function form(Schema $schema): Schema
    {
        return ContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
            'create' => CreateContract::route('/create'),
            'view' => ViewContract::route('/{record}'),
            'edit' => EditContract::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Contract est lié au tenant via employee → organisation_id (pas de FK directe).
        // L'employee_id du formulaire établit l'appartenance ; on désactive l'auto-association.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
