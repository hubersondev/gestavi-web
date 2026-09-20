<?php

namespace App\Filament\Farm\Resources\Accounts;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Farm\Resources\Accounts\Pages\EditAccount;
use App\Filament\Farm\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Farm\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Farm\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Farm\Resources\Accounts\Schemas\AccountInfolist;
use App\Filament\Farm\Resources\Accounts\Tables\AccountsTable;
use App\Models\Account;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AccountResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?string $modelLabel = 'Compte';

    protected static ?string $pluralModelLabel = 'Comptes';

    protected static ?int $navigationSort = 1;

    protected static string $permissionGroup = 'accounts';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Accounting];
    }

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'view' => ViewAccount::route('/{record}'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
