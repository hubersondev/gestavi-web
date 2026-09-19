<?php

namespace App\Filament\Farm\Resources\VetVisits\Schemas;

use App\Enums\VetVisitType;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Veterinarian;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class VetVisitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Visite vétérinaire')
                    ->tabs([
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Section::make('Contexte')
                                    ->schema([
                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->when($membership, fn (Builder $q) => $q->accessibleBy($membership))
                                                    ->pluck('name', 'id');
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->default(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership
                                                    ? app(CurrentFarm::class)->get($membership)?->id
                                                    : null;
                                            })
                                            ->hidden(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership
                                                    && app(CurrentFarm::class)->get($membership) !== null;
                                            })
                                            ->columnSpan(2),

                                        Select::make('flock_id')
                                            ->label('Bande (optionnel)')
                                            ->options(function (Get $get) {
                                                $farmId = $get('farm_id');

                                                if (! $farmId) {
                                                    $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());
                                                    $currentFarm = $membership ? app(CurrentFarm::class)->get($membership) : null;
                                                    $farmId = $currentFarm?->id;
                                                }

                                                if (! $farmId) {
                                                    return [];
                                                }

                                                return Flock::query()
                                                    ->whereHas('building', fn (Builder $q) => $q->where('farm_id', $farmId))
                                                    ->orderByDesc('start_date')
                                                    ->get()
                                                    ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.($f->name ? ' — '.$f->name : '')])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Toute la ferme')
                                            ->helperText('Laisser vide si la visite concerne toute la ferme'),

                                        Select::make('veterinarian_id')
                                            ->label('Vétérinaire')
                                            ->options(function () {
                                                return Veterinarian::query()
                                                    ->where('organisation_id', Filament::getTenant()?->getKey())
                                                    ->active()
                                                    ->get()
                                                    ->mapWithKeys(fn (Veterinarian $v) => [$v->id => $v->getFullLabel()])
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload(),

                                        Select::make('visit_type')
                                            ->label('Type de visite')
                                            ->options(VetVisitType::class)
                                            ->required()
                                            ->native(false),

                                        DatePicker::make('visit_date')
                                            ->label('Date de la visite')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now()),

                                        TimePicker::make('visit_time')
                                            ->label('Heure')
                                            ->seconds(false)
                                            ->native(false),
                                    ])
                                    ->columns(2),

                                Section::make('Motif')
                                    ->schema([
                                        Textarea::make('reason')
                                            ->label('Motif de la visite')
                                            ->required()
                                            ->rows(3)
                                            ->placeholder('Décrivez la raison de cette visite...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Observations & Diagnostic')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Section::make('Observations cliniques')
                                    ->schema([
                                        Textarea::make('symptoms_observed')
                                            ->label('Symptômes observés')
                                            ->rows(4)
                                            ->placeholder('Listez les symptômes constatés...')
                                            ->columnSpanFull(),

                                        TextInput::make('birds_examined')
                                            ->label('Sujets examinés')
                                            ->numeric()
                                            ->minValue(0),

                                        TextInput::make('birds_affected')
                                            ->label('Sujets affectés')
                                            ->numeric()
                                            ->minValue(0),
                                    ])
                                    ->columns(2),

                                Section::make('Diagnostic')
                                    ->schema([
                                        Textarea::make('diagnosis')
                                            ->label('Diagnostic')
                                            ->rows(4)
                                            ->placeholder('Diagnostic établi par le vétérinaire...')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Prélèvements')
                                    ->schema([
                                        Toggle::make('samples_taken')
                                            ->label('Prélèvements effectués')
                                            ->live()
                                            ->inline(false),

                                        Textarea::make('samples_description')
                                            ->label('Description des prélèvements')
                                            ->rows(3)
                                            ->visible(fn (Get $get) => $get('samples_taken'))
                                            ->columnSpanFull(),

                                        Textarea::make('lab_results')
                                            ->label('Résultats de laboratoire')
                                            ->rows(4)
                                            ->placeholder('Résultats des analyses...')
                                            ->visible(fn (Get $get) => $get('samples_taken'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Traitement & Recommandations')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                Section::make('Prescriptions')
                                    ->schema([
                                        Textarea::make('prescriptions')
                                            ->label('Prescriptions / Traitements')
                                            ->rows(5)
                                            ->placeholder('Médicaments prescrits, posologie, durée du traitement...')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Recommandations')
                                    ->schema([
                                        Textarea::make('recommendations')
                                            ->label('Recommandations')
                                            ->rows(4)
                                            ->placeholder('Conseils et recommandations du vétérinaire...')
                                            ->columnSpanFull(),

                                        Textarea::make('preventive_measures')
                                            ->label('Mesures préventives')
                                            ->rows(4)
                                            ->placeholder('Actions préventives à mettre en place...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Financier')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Coûts')
                                    ->schema([
                                        TextInput::make('consultation_fee')
                                            ->label('Honoraires de consultation')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->prefix('FCFA')
                                            ->live(onBlur: true),

                                        TextInput::make('medication_cost')
                                            ->label('Coût des médicaments')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->prefix('FCFA')
                                            ->live(onBlur: true),

                                        TextInput::make('total_cost')
                                            ->label('Coût total')
                                            ->numeric()
                                            ->prefix('FCFA')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('Calculé automatiquement'),
                                    ])
                                    ->columns(2),

                                Section::make('Paiement')
                                    ->schema([
                                        Toggle::make('is_paid')
                                            ->label('Payé')
                                            ->live()
                                            ->inline(false),

                                        DatePicker::make('paid_at')
                                            ->label('Date de paiement')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->visible(fn (Get $get) => $get('is_paid')),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Suivi')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                Section::make('Visite de suivi')
                                    ->schema([
                                        Toggle::make('follow_up_required')
                                            ->label('Suivi requis')
                                            ->live()
                                            ->inline(false),

                                        DatePicker::make('next_visit_date')
                                            ->label('Date de la prochaine visite')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->visible(fn (Get $get) => $get('follow_up_required')),
                                    ])
                                    ->columns(2),

                                Section::make()
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes internes')
                                            ->rows(5)
                                            ->placeholder('Notes complémentaires...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
