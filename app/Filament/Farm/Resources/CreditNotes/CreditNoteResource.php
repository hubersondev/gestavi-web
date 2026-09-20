<?php

namespace App\Filament\Farm\Resources\CreditNotes;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\CreditNotes\Pages\CreateCreditNote;
use App\Filament\Farm\Resources\CreditNotes\Pages\EditCreditNote;
use App\Filament\Farm\Resources\CreditNotes\Pages\ListCreditNotes;
use App\Filament\Farm\Resources\CreditNotes\Pages\ViewCreditNote;
use App\Filament\Farm\Resources\CreditNotes\Schemas\CreditNoteForm;
use App\Filament\Farm\Resources\CreditNotes\Schemas\CreditNoteInfolist;
use App\Filament\Farm\Resources\CreditNotes\Tables\CreditNotesTable;
use App\Models\CreditNote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CreditNoteResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = CreditNote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?string $modelLabel = 'Avoir';

    protected static ?string $pluralModelLabel = 'Avoirs';

    protected static ?int $navigationSort = 5;

    protected static string $permissionGroup = 'credit_notes';

    protected static ?string $recordTitleAttribute = 'credit_note_number';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::CreditNotes];
    }

    public static function form(Schema $schema): Schema
    {
        return CreditNoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CreditNoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditNotesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditNotes::route('/'),
            'create' => CreateCreditNote::route('/create'),
            'view' => ViewCreditNote::route('/{record}'),
            'edit' => EditCreditNote::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
