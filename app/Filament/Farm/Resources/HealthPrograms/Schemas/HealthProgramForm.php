<?php

namespace App\Filament\Farm\Resources\HealthPrograms\Schemas;

use App\Models\ChickenType;
use App\Models\Farm;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class HealthProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        // Onglet 1 : Informations générales
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Portée du programme')
                                    ->description('Définir si ce programme est global ou spécifique à une ferme')
                                    ->schema([
                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->when($membership, fn ($q) => $q->accessibleBy($membership))
                                                    ->where('is_active', true)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->default(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership
                                                    ? app(CurrentFarm::class)->get($membership)?->id
                                                    : null;
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->placeholder('Toutes les fermes (programme général)')
                                            ->helperText('Laisser vide pour un programme applicable à toutes les fermes'),

                                        Select::make('chicken_type_id')
                                            ->label('Type de volaille')
                                            ->options(fn () => ChickenType::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                            ->searchable()
                                            ->nullable()
                                            ->placeholder('Tous types (applicable à tous)')
                                            ->helperText('Laisser vide pour un programme applicable à tous les types'),

                                        Toggle::make('is_mandatory')
                                            ->label('Traitement obligatoire')
                                            ->helperText('Indique si ce traitement est obligatoire ou recommandé.')
                                            ->inline(false),
                                    ]),
                                Section::make('Traitement')
                                    ->schema([
                                        TextInput::make('treatment_name')
                                            ->label('Nom du traitement')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex: Gumboro, Newcastle, Vitamine C...'),

                                        Select::make('treatment_type')
                                            ->label('Type de traitement')
                                            ->required()
                                            ->options([
                                                'vaccin' => 'Vaccin',
                                                'vermifuge' => 'Vermifuge',
                                                'anticoccidien' => 'Anticoccidien',
                                                'vitamine' => 'Vitamine / Complément',
                                                'antibiotique' => 'Antibiotique',
                                                'antiparasitaire' => 'Antiparasitaire',
                                                'desinfectant' => 'Désinfectant',
                                                'autre' => 'Autre',
                                            ])
                                            ->native(false)
                                            ->searchable(),
                                        TextInput::make('scheduled_day')
                                            ->label('Jour prévu')
                                            ->required()
                                            ->numeric()
                                            ->minValue(1)
                                            ->suffix('jours')
                                            ->placeholder('Ex: 7')
                                            ->helperText('Âge du sujet pour l\'administration du traitement'),

                                    ])
                                    ->columns(2),
                            ]),
                        // Onglet 2 : Administration
                        Tabs\Tab::make('Administration')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                Section::make('Mode d\'administration')
                                    ->schema([
                                        Select::make('administration_route')
                                            ->label('Voie d\'administration')
                                            ->options([
                                                'eau' => 'Dans l\'eau de boisson',
                                                'injection' => 'Par injection',
                                                'spray' => 'Par nébulisation',
                                                'oculaire' => 'Goutte oculaire',
                                                'aliment' => 'Dans l\'aliment',
                                            ])
                                            ->native(false)
                                            ->nullable(),

                                        TextInput::make('dosage')
                                            ->label('Dosage')
                                            ->maxLength(100)
                                            ->placeholder('Ex: 0,5 ml/L, 1 dose/sujet...'),

                                    ])
                                    ->columns(2),
                                Section::make('Délai d\'attente')
                                    ->description('Temps d\'attente avant abattage ou vente d\'œufs')
                                    ->schema([
                                        TextInput::make('withdrawal_days')
                                            ->label('Délai d\'attente')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('jours')
                                            ->helperText('Nombre de jours avant abattage/vente après traitement'),
                                    ])
                                    ->columns(2),
                            ]),
                        // Onglet 3 : Notes
                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes et recommandations')
                                            ->rows(6)
                                            ->placeholder('Instructions spécifiques, précautions, observations...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
