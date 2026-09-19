<?php

namespace App\Filament\Farm\Resources\Transactions;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Farm\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Farm\Resources\Transactions\Pages\ViewTransaction;
use App\Filament\Farm\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Farm\Resources\Transactions\Schemas\TransactionInfolist;
use App\Filament\Farm\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TransactionResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transactions';

    protected static ?int $navigationSort = 2;

    protected static string $permissionGroup = 'transactions';

    /**
     * Une écriture de trésorerie est intangible : elle ne se modifie ni ne se
     * supprime, elle se contrepasse (TransactionService::reverseTransaction).
     */
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

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Accounting];
    }

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'view' => ViewTransaction::route('/{record}'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $currentFarm = static::currentFarm();

        return $query->whereHas('account', function (Builder $q) use ($tenant, $currentFarm): void {
            $q->where('organisation_id', $tenant?->getKey());

            if ($currentFarm) {
                $q->where(function (Builder $inner) use ($currentFarm): void {
                    $inner->where('farm_id', $currentFarm->id)->orWhereNull('farm_id');
                });
            }
        });
    }
}
