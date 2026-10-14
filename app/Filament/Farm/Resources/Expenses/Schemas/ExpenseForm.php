<?php

namespace App\Filament\Farm\Resources\Expenses\Schemas;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Filament\Farm\Components\FiscalYearDatePicker;
use App\Models\Account;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Supplier;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Dépense')
                    ->tabs([

                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Identification')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('expense_number')
                                            ->label('N° Dépense')
                                            ->maxLength(50)
                                            ->disabled()
                                            ->dehydrated()
                                            ->placeholder('Généré automatiquement (DEP-YYYY-XXXXX)'),

                                        Select::make('category')
                                            ->label('Catégorie')
                                            ->options(ExpenseCategory::class)
                                            ->required()
                                            ->searchable()
                                            ->native(false),

                                        TextInput::make('description')
                                            ->label('Description')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex : Achat de 20 sacs d\'aliment démarrage')
                                            ->columnSpanFull(),

                                        Select::make('supplier_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('organisation_id', Filament::getTenant()?->getKey())->orWhereNull('organisation_id'))])
                                            ->label('Fournisseur')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Supplier::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'farm_id', allowOrganisationLevel: true))
                                                    ->where('is_active', true)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('Optionnel'),

                                        TextInput::make('reference')
                                            ->label('N° facture fournisseur')
                                            ->maxLength(255)
                                            ->helperText('Référence du document émis par le fournisseur'),
                                    ]),

                                Section::make('Montants & dates')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('amount')
                                            ->label('Montant TTC')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                            ->suffix('XOF')
                                            ->disabledOn('edit')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTax($get, $set))
                                            ->helperText('Ne peut plus être modifié après création'),

                                        Select::make('tax_percent')
                                            ->label('TVA')
                                            ->options(['0' => 'Exonéré (0 %)', '18' => 'TVA 18 %'])
                                            ->default('0')
                                            ->required()
                                            ->native(false)
                                            ->disabledOn('edit')
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTax($get, $set))
                                            ->helperText('TVA incluse dans le montant TTC (récupérable, compte 4452)'),

                                        TextInput::make('tax_amount')
                                            ->label('dont TVA')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF')
                                            ->disabled()
                                            ->dehydrated()
                                            ->helperText('Calculée : TTC × taux / (100 + taux)'),

                                        FiscalYearDatePicker::make('expense_date')
                                            ->label('Date de la dépense')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->required(),

                                        DatePicker::make('due_date')
                                            ->label('Date d\'échéance')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->helperText('Pour les dépenses à payer plus tard')
                                            ->afterOrEqual('expense_date'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Analytique')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Section::make('Affectation')
                                    ->description('Permet de répartir les coûts par ferme et par bande')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('farm_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('farms', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                                            ->label('Ferme')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'id'))
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('Laisser vide pour une dépense de niveau organisation'),

                                        Select::make('flock_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('flocks', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                                            ->label('Bande liée')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Flock::query()
                                                    ->whereHas('building.farm', fn ($q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
                                                    ->orderByDesc('start_date')
                                                    ->get()
                                                    ->mapWithKeys(fn (Flock $flock) => [$flock->id => $flock->code.' – '.$flock->name])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Optionnel — permet de calculer le coût réel d\'une bande'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Justificatif')
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Section::make('Pièce justificative')
                                    ->description('Reçu, facture fournisseur, bon de livraison…')
                                    ->schema([
                                        FileUpload::make('attachment')
                                            ->label('Document')
                                            ->disk('local')
                                            ->visibility('private')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->directory('expenses/attachments')
                                            ->maxSize(5120)
                                            ->downloadable()
                                            ->openable()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Paiement')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Compte & statut')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('account_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('accounts', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                                            ->label('Compte à débiter')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Account::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
                                                    ->active()
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Nécessaire pour enregistrer un paiement'),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(ExpenseStatus::class)
                                            ->default(ExpenseStatus::Unpaid)
                                            ->required()
                                            ->native(false)
                                            ->disabled()
                                            ->dehydrated(fn (string $operation): bool => $operation === 'create')
                                            ->helperText('Calculé par les paiements enregistrés et l\'annulation.'),

                                        Toggle::make('pay_now')
                                            ->label('Payer immédiatement')
                                            ->helperText('Débite automatiquement le compte choisi du montant total dès la création')
                                            ->dehydrated(false)
                                            ->visible(fn ($livewire): bool => $livewire instanceof \Filament\Resources\Pages\CreateRecord)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes internes')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * TVA incluse dans le TTC : tax = TTC × taux / (100 + taux), arrondie au franc.
     */
    private static function recalculateTax(Get $get, Set $set): void
    {
        $amount = (float) ($get('amount') ?? 0);
        $rate = (float) ($get('tax_percent') ?? 0);

        $set('tax_amount', $rate > 0 && $amount > 0 ? round($amount * $rate / (100 + $rate), 0) : 0);
    }
}
