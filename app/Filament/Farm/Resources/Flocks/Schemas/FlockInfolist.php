<?php

namespace App\Filament\Farm\Resources\Flocks\Schemas;

use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class FlockInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        Tabs\Tab::make("Vue d'ensemble")
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Section::make('Informations générales')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('building.farm.name')
                                            ->label('Ferme')
                                            ->weight('bold')
                                            ->hidden(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership && app(CurrentFarm::class)->get($membership) !== null;
                                            }),

                                        TextEntry::make('building.name')
                                            ->label('Bâtiment')
                                            ->weight('bold'),

                                        TextEntry::make('code')
                                            ->label('Code'),

                                        TextEntry::make('name')
                                            ->label('Nom de la bande')
                                            ->weight('bold')
                                            ->placeholder('-'),

                                        TextEntry::make('chickenType.name')
                                            ->label('Type de volaille')
                                            ->badge()
                                            ->color('info')
                                            ->placeholder('-'),

                                        TextEntry::make('breed')
                                            ->label('Race')
                                            ->placeholder('-'),

                                        TextEntry::make('supplier.name')
                                            ->label('Fournisseur')
                                            ->placeholder('-'),

                                        TextEntry::make('status')
                                            ->label('Statut')
                                            ->badge()
                                            ->color(fn ($state) => $state->color()),
                                    ]),

                                Section::make('Dates & Effectifs')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('start_date')
                                            ->label('Mise en place')
                                            ->date('d/m/Y')
                                            ->placeholder('-'),

                                        TextEntry::make('expected_end_date')
                                            ->label('Fin prévue')
                                            ->date('d/m/Y')
                                            ->placeholder('En cours'),

                                        TextEntry::make('actual_end_date')
                                            ->label('Fin réelle')
                                            ->date('d/m/Y')
                                            ->placeholder('-'),

                                        TextEntry::make('age_at_arrival')
                                            ->label('Âge à la réception')
                                            ->suffix(' jours'),

                                        TextEntry::make('quantity_started')
                                            ->label('Effectif initial')
                                            ->numeric()
                                            ->suffix(' sujets'),

                                        TextEntry::make('quantity_current')
                                            ->label('Effectif actuel')
                                            ->numeric()
                                            ->suffix(' sujets')
                                            ->weight('bold'),
                                    ]),

                                Section::make('Performances zootechniques')
                                    ->description('Indicateurs calculés à partir des relevés quotidiens et des ventes de sujets')
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('perf_mortality')
                                            ->label('Taux de mortalité')
                                            ->state(fn ($record) => number_format($record->getMortalityRate(), 2, ',', ' ').' %')
                                            ->color(fn ($record) => match (true) {
                                                $record->getMortalityRate() > 5 => 'danger',
                                                $record->getMortalityRate() > 2 => 'warning',
                                                default => 'success',
                                            })
                                            ->badge(),

                                        TextEntry::make('perf_culls')
                                            ->label('Taux de réforme')
                                            ->state(fn ($record) => number_format($record->getCullRate(), 2, ',', ' ').' %'),

                                        TextEntry::make('perf_sold')
                                            ->label('Sujets vendus')
                                            ->state(fn ($record) => number_format((int) $record->quantity_sold, 0, ',', ' ')),

                                        TextEntry::make('perf_feed')
                                            ->label('Aliment consommé')
                                            ->state(fn ($record) => number_format((float) $record->dailyRecords()->sum('feed_consumed_kg'), 1, ',', ' ').' kg'),

                                        TextEntry::make('perf_gmq')
                                            ->label('GMQ')
                                            ->state(fn ($record) => self::indicator($record, 'gmq', ' g/j'))
                                            ->visible(fn ($record) => $record->productionKind()->tracksWeight()),

                                        TextEntry::make('perf_ic')
                                            ->label('Indice de consommation')
                                            ->state(fn ($record) => self::indicator($record, 'ic'))
                                            ->helperText('kg d\'aliment par kg de poids vif produit')
                                            ->visible(fn ($record) => $record->productionKind()->tracksWeight()),

                                        TextEntry::make('perf_ipe')
                                            ->label('IPE (EPEF)')
                                            ->state(fn ($record) => self::indicator($record, 'ipe'))
                                            ->visible(fn ($record) => $record->productionKind()->tracksWeight()),

                                        TextEntry::make('perf_laying')
                                            ->label('Taux de ponte moyen')
                                            ->state(fn ($record) => self::indicator($record, 'taux_ponte_moyen', ' %'))
                                            ->visible(fn ($record) => $record->productionKind()->tracksEggs()),

                                        TextEntry::make('perf_hhp')
                                            ->label('Œufs par poule logée')
                                            ->state(fn ($record) => self::indicator($record, 'oeufs_par_poule_logee'))
                                            ->visible(fn ($record) => $record->productionKind()->tracksEggs()),

                                        TextEntry::make('perf_withdrawal')
                                            ->label('Délai d\'attente')
                                            ->state(fn ($record) => $record->isUnderWithdrawal()
                                                ? 'Vente interdite jusqu\'au '.$record->getWithdrawalUntil()?->format('d/m/Y')
                                                : 'Aucun')
                                            ->color(fn ($record) => $record->isUnderWithdrawal() ? 'danger' : 'success')
                                            ->badge(),
                                    ]),

                                Section::make('Phase de production actuelle')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('current_phase_name')
                                            ->label('Phase')
                                            ->state(fn ($record) => $record->getCurrentPhase()?->name)
                                            ->placeholder('—')
                                            ->badge()
                                            ->color('primary'),

                                        TextEntry::make('current_phase_period')
                                            ->label('Période')
                                            ->state(function ($record) {
                                                $phase = $record->getCurrentPhase();

                                                return $phase
                                                    ? "J{$phase->start_day} – J{$phase->end_day}"
                                                    : null;
                                            })
                                            ->placeholder('—'),

                                        TextEntry::make('current_age')
                                            ->label('Âge actuel')
                                            ->state(function ($record) {
                                                $days = $record->getCurrentAgeInDays();

                                                return $days !== null
                                                    ? "{$days} j (".$record->getCurrentAgeInWeeks().' sem.)'
                                                    : null;
                                            })
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Informations financières')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('purchase_price_per_unit')
                                            ->label('Prix unitaire')
                                            ->money('XOF')
                                            ->placeholder('Non renseigné'),

                                        TextEntry::make('total_purchase_price')
                                            ->label('Prix total')
                                            ->money('XOF')
                                            ->placeholder('Non renseigné'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Programme sanitaire')
                            ->icon('heroicon-o-beaker')
                            ->badge(fn ($record) => $record->vaccinations()->count() ?: null)
                            ->schema([
                                RepeatableEntry::make('vaccinations')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('treatment_name')
                                            ->label('Traitement')
                                            ->weight('bold'),
                                        TextEntry::make('treatment_type')
                                            ->label('Type')
                                            ->badge()
                                            ->color('info')
                                            ->formatStateUsing(fn ($state) => match ($state) {
                                                'vaccin' => 'Vaccin',
                                                'vermifuge' => 'Vermifuge',
                                                'anticoccidien' => 'Anticoccidien',
                                                'vitamine' => 'Vitamine',
                                                'antibiotique' => 'Antibiotique',
                                                'antiparasitaire' => 'Antiparasitaire',
                                                'desinfectant' => 'Désinfectant',
                                                default => $state ?? '—',
                                            })
                                            ->placeholder('—'),
                                        TextEntry::make('day_label')
                                            ->label('Jour')
                                            ->state(function ($record) {
                                                if ($record->age_at_treatment !== null) {
                                                    return 'J'.$record->age_at_treatment;
                                                }

                                                $flock = $record->flock;

                                                if ($flock?->start_date && $record->scheduled_date) {
                                                    $days = (int) $flock->start_date->diffInDays($record->scheduled_date, false);

                                                    return 'J'.($flock->age_at_arrival + $days);
                                                }

                                                return '—';
                                            }),
                                        TextEntry::make('scheduled_date')
                                            ->label('Date prévue')
                                            ->date('d/m/Y'),
                                        TextEntry::make('dosage')
                                            ->label('Dose')
                                            ->placeholder('—'),
                                        TextEntry::make('status')
                                            ->label('Statut')
                                            ->badge()
                                            ->color(fn ($state) => $state?->color())
                                            ->formatStateUsing(fn ($state) => $state?->label() ?? $state),
                                    ])
                                    ->columns(6)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('Observations')
                                            ->placeholder('Aucune note')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Formate un indicateur calculé par FlockService (null → « — »).
     */
    private static function indicator(\App\Models\Flock $flock, string $key, string $suffix = ''): string
    {
        static $cache = [];

        $cache[$flock->getKey()] ??= app(\App\Services\FlockService::class)->calculatePerformanceIndicators($flock);
        $value = $cache[$flock->getKey()][$key] ?? null;

        return $value === null ? '—' : number_format((float) $value, 2, ',', ' ').$suffix;
    }
}
