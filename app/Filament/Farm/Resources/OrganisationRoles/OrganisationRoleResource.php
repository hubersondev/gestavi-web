<?php

namespace App\Filament\Farm\Resources\OrganisationRoles;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\OrganisationRoles\Pages\CreateOrganisationRole;
use App\Filament\Farm\Resources\OrganisationRoles\Pages\EditOrganisationRole;
use App\Filament\Farm\Resources\OrganisationRoles\Pages\ListOrganisationRoles;
use App\Filament\Farm\Resources\OrganisationRoles\Pages\ViewOrganisationRole;
use App\Filament\Farm\Resources\OrganisationRoles\Schemas\OrganisationRoleForm;
use App\Filament\Farm\Resources\OrganisationRoles\Schemas\OrganisationRoleInfolist;
use App\Filament\Farm\Resources\OrganisationRoles\Tables\OrganisationRolesTable;
use App\Models\OrganisationRole;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrganisationRoleResource extends Resource
{
    use RequiresPlanFeatures;
    use ChecksOrganisationPermissions;

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::TeamManagement];
    }
    protected static ?string $model = OrganisationRole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'Rôle';

    protected static ?string $pluralModelLabel = 'Rôles';

    protected static ?int $navigationSort = 10;

    protected static string $permissionGroup = 'roles';

    public static function form(Schema $schema): Schema
    {
        return OrganisationRoleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrganisationRoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganisationRolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisationRoles::route('/'),
            'create' => CreateOrganisationRole::route('/create'),
            'view' => ViewOrganisationRole::route('/{record}'),
            'edit' => EditOrganisationRole::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organisation_id', Filament::getTenant()?->id);
    }
}
