<?php

namespace App\Filament\Farm\Resources\AccountTransfers;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\AccountTransfers\Pages\CreateAccountTransfer;
use App\Filament\Farm\Resources\AccountTransfers\Pages\EditAccountTransfer;
use App\Filament\Farm\Resources\AccountTransfers\Pages\ListAccountTransfers;
use App\Filament\Farm\Resources\AccountTransfers\Pages\ViewAccountTransfer;
use App\Filament\Farm\Resources\AccountTransfers\Schemas\AccountTransferForm;
use App\Filament\Farm\Resources\AccountTransfers\Schemas\AccountTransferInfolist;
use App\Filament\Farm\Resources\AccountTransfers\Tables\AccountTransfersTable;
use App\Models\AccountTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AccountTransferResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = AccountTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?string $modelLabel = 'Transfert';

    protected static ?string $pluralModelLabel = 'Transferts entre comptes';

    protected static ?int $navigationSort = 3;

    protected static string $permissionGroup = 'account_transfers';

    /**
     * Seul un transfert encore en attente (aucun compte mouvementé) peut être
     * modifié ou supprimé ; un transfert complété se contrepasse.
     */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof AccountTransfer
            && $record->isPending()
            && static::memberHasPermission('update');
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof AccountTransfer
            && $record->isPending()
            && static::memberHasPermission('delete');
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Transfers];
    }

    public static function form(Schema $schema): Schema
    {
        return AccountTransferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccountTransferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountTransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountTransfers::route('/'),
            'create' => CreateAccountTransfer::route('/create'),
            'view' => ViewAccountTransfer::route('/{record}'),
            'edit' => EditAccountTransfer::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->where('organisation_id', $tenant?->getKey());
    }
}
