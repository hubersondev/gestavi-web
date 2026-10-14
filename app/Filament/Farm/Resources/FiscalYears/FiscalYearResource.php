<?php

namespace App\Filament\Farm\Resources\FiscalYears;

use App\Enums\FiscalYearStatus;
use App\Enums\PlanFeature;
use App\Exceptions\AccountingException;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\FiscalYears\Pages\CreateFiscalYear;
use App\Filament\Farm\Resources\FiscalYears\Pages\EditFiscalYear;
use App\Filament\Farm\Resources\FiscalYears\Pages\ListFiscalYears;
use App\Models\FiscalYear;
use App\Services\AccountingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Exercices comptables de l'organisation : création, clôture (détermination du résultat),
 * verrouillage des périodes clôturées.
 */
class FiscalYearResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;

    protected static ?string $model = FiscalYear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Comptabilité';

    protected static ?string $modelLabel = 'Exercice comptable';

    protected static ?string $pluralModelLabel = 'Exercices comptables';

    protected static ?int $navigationSort = 8;

    protected static string $permissionGroup = 'fiscal_years';

    protected static ?string $recordTitleAttribute = 'label';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Accounting];
    }

    /** Un exercice clôturé ne se modifie plus. */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof FiscalYear && $record->isOpen() && static::memberHasPermission('update');
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof FiscalYear
            && $record->isOpen()
            && ! $record->entries()->exists()
            && static::memberHasPermission('delete');
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Exercice')
                ->columns(3)
                ->schema([
                    TextInput::make('label')
                        ->label('Libellé')
                        ->required()
                        ->maxLength(50)
                        ->placeholder('Ex. 2026')
                        ->rules(fn (?FiscalYear $record): array => [
                            Rule::unique('fiscal_years', 'label')
                                ->where('organisation_id', Filament::getTenant()?->getKey())
                                ->ignore($record?->getKey()),
                        ]),
                    DatePicker::make('starts_on')
                        ->label('Début')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()->startOfYear()),
                    DatePicker::make('ends_on')
                        ->label('Fin')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()->endOfYear())
                        ->afterOrEqual('starts_on')
                        ->rules([
                            fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                // Vérification du chevauchement effectuée dans mutateFormDataBeforeCreate/Save
                            },
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('label')->label('Exercice')->weight('bold')->searchable(),
                TextColumn::make('starts_on')->label('Début')->date('d/m/Y'),
                TextColumn::make('ends_on')->label('Fin')->date('d/m/Y'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('entries_count')->label('Écritures')->counts('entries'),
                TextColumn::make('net_result')
                    ->label('Résultat net')
                    ->numeric(0, thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->placeholder('—')
                    ->color(fn ($state) => $state === null ? null : ((float) $state < 0 ? 'danger' : 'success')),
                TextColumn::make('closed_at')->label('Clôturé le')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('close')
                    ->label('Clôturer')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Clôturer l\'exercice')
                    ->modalDescription('Les classes 6 et 7 seront soldées vers le compte 131 (résultat net), les soldes des comptes de bilan seront reportés en à-nouveaux (journal AN) au lendemain de la fin d\'exercice, et plus aucune écriture ni transaction ne pourra être datée dans cet exercice. Cette action est irréversible.')
                    ->visible(fn (FiscalYear $record): bool => $record->isOpen() && static::canPerformAction('close'))
                    ->action(function (FiscalYear $record): void {
                        try {
                            $closed = app(AccountingService::class)->closeFiscalYear($record, Filament::auth()->id());

                            Notification::make()
                                ->title('Exercice clôturé')
                                ->body('Résultat net : '.number_format((float) $closed->net_result, 0, ',', ' ').' XOF')
                                ->success()
                                ->send();
                        } catch (AccountingException $e) {
                            Notification::make()->title('Clôture impossible')->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiscalYears::route('/'),
            'create' => CreateFiscalYear::route('/create'),
            'edit' => EditFiscalYear::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('organisation_id', Filament::getTenant()?->getKey());
    }

    /**
     * Refuse un exercice qui chevauche un exercice existant de l'organisation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function assertNoOverlap(array $data, ?int $ignoreId = null): void
    {
        $overlap = FiscalYear::query()
            ->where('organisation_id', Filament::getTenant()?->getKey())
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->first();

        if ($overlap) {
            throw AccountingException::overlappingFiscalYear($overlap->label);
        }
    }

    public static function statusOptions(): array
    {
        return collect(FiscalYearStatus::cases())->mapWithKeys(fn (FiscalYearStatus $s) => [$s->value => $s->getLabel()])->all();
    }
}
