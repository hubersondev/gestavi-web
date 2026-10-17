<?php

namespace App\Filament\Farm\Resources\BankReconciliations;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\BankReconciliations\Pages\CreateBankReconciliation;
use App\Filament\Farm\Resources\BankReconciliations\Pages\EditBankReconciliation;
use App\Filament\Farm\Resources\BankReconciliations\Pages\ListBankReconciliations;
use App\Filament\Farm\Resources\BankReconciliations\Pages\ViewBankReconciliation;
use App\Filament\Farm\Resources\BankReconciliations\RelationManagers\StatementLinesRelationManager;
use App\Filament\Farm\Resources\BankReconciliations\RelationManagers\TransactionsRelationManager;
use App\Filament\Farm\Resources\BankReconciliations\Schemas\BankReconciliationForm;
use App\Filament\Farm\Resources\BankReconciliations\Schemas\BankReconciliationInfolist;
use App\Filament\Farm\Resources\BankReconciliations\Tables\BankReconciliationsTable;
use App\Models\BankReconciliation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Rapprochement bancaire par relevé : un relevé par compte et par date, lignes importées,
 * transactions pointées, clôture à écart nul. Réservé au plan incluant la fonctionnalité.
 */
class BankReconciliationResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    /** Événement Livewire émis après chaque pointage pour rafraîchir la synthèse et les tableaux. */
    public const string REFRESH_EVENT = 'bank-reconciliation-updated';

    protected static ?string $model = BankReconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?string $modelLabel = 'Rapprochement bancaire';

    protected static ?string $pluralModelLabel = 'Rapprochements bancaires';

    protected static ?string $navigationLabel = 'Rapprochement bancaire';

    protected static ?int $navigationSort = 9;

    protected static string $permissionGroup = 'bank_reconciliations';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::BankReconciliation];
    }

    /** Seul un rapprochement en cours se modifie ou se supprime ; clôturé, il reste consultable. */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof BankReconciliation
            && $record->isDraft()
            && static::memberHasPermission('update');
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof BankReconciliation
            && $record->isDraft()
            && static::memberHasPermission('delete');
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof BankReconciliation ? $record->label() : static::getModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return BankReconciliationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BankReconciliationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankReconciliationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
            StatementLinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankReconciliations::route('/'),
            'create' => CreateBankReconciliation::route('/create'),
            'view' => ViewBankReconciliation::route('/{record}'),
            'edit' => EditBankReconciliation::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
