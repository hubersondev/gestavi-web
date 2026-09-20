<?php

namespace App\Filament\Farm\Resources\JournalEntries;

use App\Enums\JournalCode;
use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Filament\Farm\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Models\JournalEntry;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Journal comptable en lecture seule : les écritures sont générées par les
 * événements métier et ne s'éditent jamais (extourne uniquement).
 */
class JournalEntryResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?string $modelLabel = 'Écriture comptable';

    protected static ?string $pluralModelLabel = 'Journal comptable';

    protected static ?int $navigationSort = 7;

    protected static string $permissionGroup = 'transactions';

    protected static ?string $recordTitleAttribute = 'entry_number';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Accounting];
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Écriture')
                ->columns(4)
                ->schema([
                    TextEntry::make('entry_number')->label('N° pièce')->badge()->color('primary'),
                    TextEntry::make('entry_date')->label('Date')->date('d/m/Y'),
                    TextEntry::make('journal')->label('Journal')->badge(),
                    TextEntry::make('farm.name')->label('Ferme')->placeholder('Organisation'),
                    TextEntry::make('label')->label('Libellé')->columnSpan(2),
                    TextEntry::make('reference_number')->label('Référence')->placeholder('—'),
                    TextEntry::make('fiscalYear.label')->label('Exercice')->placeholder('—'),
                    TextEntry::make('is_reversal')
                        ->label('Nature')
                        ->state(fn (JournalEntry $record): string => $record->is_reversal ? 'Extourne de '.$record->reversedEntry?->entry_number : 'Écriture')
                        ->badge()
                        ->color(fn (JournalEntry $record): string => $record->is_reversal ? 'warning' : 'success'),
                ]),
            Section::make('Lignes')
                ->schema([
                    RepeatableEntry::make('lines')
                        ->label('')
                        ->columns(5)
                        ->schema([
                            TextEntry::make('chart_account_code')->label('Compte')->weight('bold'),
                            TextEntry::make('chart_account_label')->label('Intitulé')->columnSpan(2),
                            TextEntry::make('debit')->label('Débit')->numeric(0, thousandsSeparator: ' ')->placeholder('—'),
                            TextEntry::make('credit')->label('Crédit')->numeric(0, thousandsSeparator: ' ')->placeholder('—'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('entry_date', 'desc')
            ->columns([
                TextColumn::make('entry_number')->label('N° pièce')->badge()->color('primary')->searchable(),
                TextColumn::make('entry_date')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('journal')->label('Journal')->badge(),
                TextColumn::make('label')->label('Libellé')->searchable()->limit(60),
                TextColumn::make('farm.name')->label('Ferme')->placeholder('Organisation')->toggleable(),
                TextColumn::make('lines_sum_debit')->label('Montant')->sum('lines', 'debit')->numeric(0, thousandsSeparator: ' ')->suffix(' XOF'),
                IconColumn::make('is_reversal')->label('Extourne')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('journal')->label('Journal')->options(JournalCode::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJournalEntries::route('/'),
            'view' => ViewJournalEntry::route('/{record}'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
