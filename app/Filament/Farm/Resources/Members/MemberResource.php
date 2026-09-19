<?php

namespace App\Filament\Farm\Resources\Members;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\Members\Pages\CreateMember;
use App\Filament\Farm\Resources\Members\Pages\EditMember;
use App\Filament\Farm\Resources\Members\Pages\ListMembers;
use App\Filament\Farm\Resources\Members\Pages\ViewMember;
use App\Filament\Farm\Resources\Members\Schemas\MemberForm;
use App\Filament\Farm\Resources\Members\Schemas\MemberInfolist;
use App\Filament\Farm\Resources\Members\Tables\MembersTable;
use App\Models\OrganisationUser;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MemberResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::TeamManagement];
    }

    protected static ?string $model = OrganisationUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    protected static ?int $navigationSort = 1;

    protected static string $permissionGroup = 'members';

    /**
     * Un membre ne peut modifier ou supprimer qu'un membre de rôle strictement
     * inférieur au sien (un propriétaire peut agir sur tout le monde).
     */
    public static function canEdit(Model $record): bool
    {
        return static::memberHasPermission('update') && static::actorOutranks($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::memberHasPermission('delete') && static::actorOutranks($record);
    }

    protected static function actorOutranks(Model $record): bool
    {
        if (! $record instanceof OrganisationUser) {
            return false;
        }

        $actor = Filament::auth()->user()?->membershipIn(Filament::getTenant());

        if (! $actor) {
            return false;
        }

        if ($actor->isOwner()) {
            return true;
        }

        return ($actor->organisationRole?->weight ?? 0) > ($record->organisationRole?->weight ?? 0);
    }

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MemberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
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
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'organisationRole', 'farms'])
            ->where('organisation_id', Filament::getTenant()?->id);
    }
}
