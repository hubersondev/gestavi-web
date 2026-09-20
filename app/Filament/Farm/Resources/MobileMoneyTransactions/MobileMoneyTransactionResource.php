<?php

namespace App\Filament\Farm\Resources\MobileMoneyTransactions;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\MobileMoneyTransactions\Pages\ListMobileMoneyTransactions;
use App\Filament\Farm\Resources\MobileMoneyTransactions\Pages\ViewMobileMoneyTransaction;
use App\Filament\Farm\Resources\MobileMoneyTransactions\Schemas\MobileMoneyTransactionInfolist;
use App\Filament\Farm\Resources\MobileMoneyTransactions\Tables\MobileMoneyTransactionsTable;
use App\Models\MobileMoneyTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Encaissements mobile money reçus par webhook : lecture, rapprochement, ignorance.
 * Aucune création ni modification manuelle : les lignes proviennent des opérateurs.
 */
class MobileMoneyTransactionResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = MobileMoneyTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?string $modelLabel = 'Encaissement mobile money';

    protected static ?string $pluralModelLabel = 'Encaissements mobile money';

    protected static ?string $slug = 'encaissements-mobile-money';

    protected static ?int $navigationSort = 6;

    protected static string $permissionGroup = 'mobile_money';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Payments];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->received()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return MobileMoneyTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MobileMoneyTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMobileMoneyTransactions::route('/'),
            'view' => ViewMobileMoneyTransaction::route('/{record}'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
