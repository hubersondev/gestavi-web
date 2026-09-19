<?php

namespace App\Services;

use App\Enums\FarmLogCategory;
use App\Enums\FarmLogPriority;
use App\Enums\VaccinationStatus;
use App\Models\DailyRecord;
use App\Models\FarmLog;
use App\Models\Flock;
use App\Models\HealthProgram;
use App\Models\Vaccination;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FlockService
{
    /**
     * Créer une bande avec workflow complet :
     * - Auto-génération du code si non fourni
     * - Calcul du prix total d'achat
     * - Génération du calendrier vaccinal depuis les programmes sanitaires
     * - Entrée automatique dans le journal de ferme
     */
    public function createFlock(array $data): Flock
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = $this->generateFlockCode($data['building_id']);
            }

            if (
                empty($data['total_purchase_price'])
                && ! empty($data['purchase_price_per_unit'])
                && ! empty($data['quantity_started'])
            ) {
                $data['total_purchase_price'] = $data['purchase_price_per_unit'] * $data['quantity_started'];
            }

            if (! isset($data['quantity_current'])) {
                $data['quantity_current'] = $data['quantity_started'];
            }

            $flock = Flock::create($data);
            $flock->load('building.farm', 'chickenType');

            $vaccinationCount = $this->generateVaccinationCalendar($flock);

            $chickenType = $flock->chickenType?->name ?? 'volailles';
            $farm = $flock->building?->farm;

            FarmLog::create([
                'farm_id' => $farm?->id,
                'flock_id' => $flock->id,
                'building_id' => $flock->building_id,
                'recorded_by' => Auth::id(),
                'log_date' => now(),
                'title' => "Nouvelle bande : {$flock->code}",
                'category' => FarmLogCategory::Production,
                'priority' => FarmLogPriority::Normal,
                'description' => "Mise en place de la bande {$flock->code} ({$chickenType})"
                    ." — {$flock->quantity_started} sujets"
                    .($flock->breed ? ", race {$flock->breed}" : '')
                    .". {$vaccinationCount} vaccination(s) programmée(s).",
            ]);

            return $flock;
        });
    }

    /**
     * Générer le calendrier vaccinal depuis les programmes sanitaires de l'organisation.
     * Ignore les traitements déjà passés selon l'âge à l'arrivée.
     * Idempotent : ne crée aucun doublon si appelé plusieurs fois.
     */
    public function generateVaccinationCalendar(Flock $flock): int
    {
        if ($flock->vaccinations()->exists()) {
            return 0;
        }

        $programs = HealthProgram::getProgramForFlock($flock);
        $count = 0;

        foreach ($programs as $program) {
            // Ignorer les traitements antérieurs à l'âge d'arrivée
            if ($program->scheduled_day < ($flock->age_at_arrival ?? 0)) {
                continue;
            }

            $scheduledDate = null;
            if ($flock->start_date) {
                $daysOffset = $program->scheduled_day - ($flock->age_at_arrival ?? 0);
                $scheduledDate = $flock->start_date->copy()->addDays(max(0, $daysOffset));
            }

            Vaccination::create([
                'flock_id' => $flock->id,
                'health_program_id' => $program->id,
                'treatment_name' => $program->treatment_name,
                'treatment_type' => $program->treatment_type,
                'administration_route' => $program->administration_route,
                'dosage' => $program->dosage,
                'withdrawal_days' => (int) ($program->withdrawal_days ?? 0),
                'scheduled_date' => $scheduledDate,
                'age_at_treatment' => $program->scheduled_day,
                'status' => VaccinationStatus::Pending,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Démarrer une bande planifiée.
     * Met à jour le statut et crée une entrée dans le journal.
     */
    public function startFlock(Flock $flock): void
    {
        if (! $flock->isPlanned()) {
            throw new \LogicException('Seule une bande planifiée peut être démarrée.');
        }

        DB::transaction(function () use ($flock) {
            $flock->start();
            $flock->refresh();

            FarmLog::create([
                'farm_id' => $flock->building?->farm?->id,
                'flock_id' => $flock->id,
                'building_id' => $flock->building_id,
                'recorded_by' => Auth::id(),
                'log_date' => now(),
                'title' => "Démarrage bande : {$flock->code}",
                'category' => FarmLogCategory::Production,
                'priority' => FarmLogPriority::Normal,
                'description' => "La bande {$flock->code} est passée en statut « En cours »."
                    ." Effectif : {$flock->quantity_current} sujets."
                    ." Date de mise en place : {$flock->start_date?->format('d/m/Y')}.",
            ]);
        });
    }

    /**
     * Clôturer une bande active.
     * Marque les vaccinations en attente comme ignorées,
     * génère un rapport de performance et crée une entrée de journal.
     */
    public function completeFlock(Flock $flock, ?string $notes = null, bool $ignoreWithdrawal = false): array
    {
        if (! $flock->isActive()) {
            throw new \LogicException('Seule une bande en cours peut être clôturée.');
        }

        // Clôturer = vendre ou abattre : interdit pendant le délai d'attente d'un traitement
        if (! $ignoreWithdrawal && $flock->isUnderWithdrawal()) {
            throw new \LogicException(sprintf(
                'La bande %s est sous délai d\'attente jusqu\'au %s : aucune vente ni abattage avant cette date.',
                $flock->code,
                $flock->getWithdrawalUntil()?->format('d/m/Y'),
            ));
        }

        return DB::transaction(function () use ($flock, $notes) {
            $flock->complete();

            if ($notes) {
                $flock->update(['notes' => $notes]);
            }

            // Marquer les vaccinations en attente comme ignorées
            $flock->vaccinations()
                ->whereIn('status', [VaccinationStatus::Pending, VaccinationStatus::Delayed])
                ->update([
                    'status' => VaccinationStatus::Skipped->value,
                    'notes' => 'Bande clôturée',
                ]);

            $flock->refresh();
            $report = $this->buildCompletionReport($flock);

            FarmLog::create([
                'farm_id' => $flock->building?->farm?->id,
                'flock_id' => $flock->id,
                'building_id' => $flock->building_id,
                'recorded_by' => Auth::id(),
                'log_date' => now(),
                'title' => "Clôture bande : {$flock->code}",
                'category' => FarmLogCategory::Production,
                'priority' => FarmLogPriority::High,
                'description' => $this->formatCompletionDescription($flock, $report),
            ]);

            return $report;
        });
    }

    /**
     * Annuler une bande (planifiée ou active).
     * Marque les vaccinations en attente comme ignorées et journalise l'annulation.
     */
    public function cancelFlock(Flock $flock, string $reason): void
    {
        if ($flock->isCompleted() || $flock->isCancelled()) {
            throw new \LogicException('Cette bande ne peut plus être annulée.');
        }

        DB::transaction(function () use ($flock, $reason) {
            $flock->update(['notes' => $reason]);
            $flock->cancel();

            $flock->vaccinations()
                ->whereIn('status', [VaccinationStatus::Pending, VaccinationStatus::Delayed])
                ->update([
                    'status' => VaccinationStatus::Skipped->value,
                    'notes' => 'Bande annulée',
                ]);

            FarmLog::create([
                'farm_id' => $flock->building?->farm?->id,
                'flock_id' => $flock->id,
                'building_id' => $flock->building_id,
                'recorded_by' => Auth::id(),
                'log_date' => now(),
                'title' => "Annulation bande : {$flock->code}",
                'category' => FarmLogCategory::Incident,
                'priority' => FarmLogPriority::Urgent,
                'description' => "Bande {$flock->code} annulée."
                    ." Motif : {$reason}."
                    ." Effectif au moment de l'annulation : {$flock->quantity_current} sujets.",
            ]);
        });
    }

    /**
     * Enregistrer les données quotidiennes avec effets automatiques :
     * - Calcul de l'âge en jours
     * - Mise à jour de l'effectif si mortalité (via l'événement `created` de DailyRecord)
     */
    public function recordDailyData(Flock $flock, array $data): DailyRecord
    {
        if (! isset($data['age_days'])) {
            $recordDate = isset($data['record_date'])
                ? \Carbon\Carbon::parse($data['record_date'])
                : now();
            $daysFromStart = $flock->start_date
                ? (int) $flock->start_date->diffInDays($recordDate)
                : 0;
            $data['age_days'] = ($flock->age_at_arrival ?? 0) + $daysFromStart;
        }

        $mortality = (int) ($data['mortality'] ?? 0);

        if ($mortality > (int) $flock->quantity_current) {
            throw new \InvalidArgumentException(sprintf(
                'La mortalité saisie (%d) dépasse l\'effectif présent de la bande %s (%d).',
                $mortality,
                $flock->code,
                (int) $flock->quantity_current,
            ));
        }

        $data['flock_id'] = $flock->id;
        $data['recorded_by'] = $data['recorded_by'] ?? Auth::id();

        return DB::transaction(fn (): DailyRecord => DailyRecord::create($data));
    }

    /**
     * Calculer les indicateurs de performance actuels de la bande (lecture seule).
     */
    public function calculatePerformanceIndicators(Flock $flock): array
    {
        $lastRecord = $flock->dailyRecords()->orderByDesc('record_date')->first();
        $kind = $flock->productionKind();

        $indicators = [
            'age_jours' => $flock->getCurrentAgeInDays(),
            'age_semaines' => $flock->getCurrentAgeInWeeks(),
            'phase_actuelle' => $flock->getCurrentPhase()?->name,
            'orientation' => $kind->getLabel(),

            'effectif_initial' => $flock->quantity_started,
            'effectif_actuel' => $flock->quantity_current,
            'sujets_vendus' => $flock->quantity_sold,
            'mortalite_totale' => $flock->getTotalMortality(),
            'reformes_totales' => $flock->getTotalCulls(),
            'pertes_totales' => $flock->getLossCount(),
            'taux_mortalite' => $flock->getMortalityRate(),
            'taux_reforme' => $flock->getCullRate(),
            'viabilite' => $flock->getViabilityRate(),

            'aliment_total_kg' => round((float) $flock->dailyRecords()->sum('feed_consumed_kg'), 1),
            'cout_par_sujet' => $flock->getCostPerCurrentUnit(),

            'vaccinations_a_jour' => $flock->vaccinations()->overdue()->doesntExist(),
            'vaccinations_en_retard' => $flock->vaccinations()->overdue()->count(),
            'prochaine_vaccination' => $this->getNextVaccinationInfo($flock),
            'delai_attente_jusqu_au' => $flock->getWithdrawalUntil()?->format('d/m/Y'),
        ];

        // Indicateurs depuis le dernier relevé quotidien
        if ($lastRecord) {
            $indicators['derniere_saisie'] = $lastRecord->record_date?->format('d/m/Y');
            $indicators['aliment_par_sujet_g'] = $lastRecord->getFeedPerBird();
            $indicators['mortalite_dernier_jour'] = $lastRecord->mortality;
        }

        // Indicateurs de ponte (pondeuses, reproducteurs, mixte)
        if ($kind->tracksEggs()) {
            $indicators['oeufs_jour'] = $lastRecord?->eggs_collected;
            $indicators['taux_ponte_jour'] = $lastRecord?->getLayingRate();
            $indicators['taux_ponte_moyen'] = $this->getAverageLayingRate($flock);
            $indicators['oeufs_total'] = $this->getTotalEggsCollected($flock);
            $indicators['oeufs_par_poule_logee'] = $this->getEggsPerHenHoused($flock);
        }

        // Indicateurs de croissance (chair, reproducteurs, mixte)
        if ($kind->tracksWeight()) {
            $indicators['poids_moyen'] = $lastRecord?->average_weight_g;
            $indicators['gmq'] = $this->calculateGMQ($flock);
            $indicators['ic'] = $this->getFeedConversionRatio($flock);
            $indicators['ipe'] = $this->getEuropeanEfficiencyFactor($flock);
        }

        return $indicators;
    }

    /**
     * Récupérer les vaccinations à venir et en retard pour une bande.
     */
    public function getUpcomingVaccinations(Flock $flock, int $daysAhead = 7): Collection
    {
        return $flock->vaccinations()
            ->whereIn('status', [VaccinationStatus::Pending->value, VaccinationStatus::Delayed->value])
            ->where(function ($query) use ($daysAhead) {
                $query->where('scheduled_date', '<', now()->toDateString())
                    ->orWhereBetween('scheduled_date', [
                        now()->toDateString(),
                        now()->addDays($daysAhead)->toDateString(),
                    ]);
            })
            ->orderBy('scheduled_date')
            ->get();
    }

    // ──────────────────────────────────────────────
    //  Méthodes privées
    // ──────────────────────────────────────────────

    /**
     * Générer un code bande séquentiel : BND-YYYY-XXX
     * Scoped par bâtiment → ferme pour l'unicité.
     */
    private function generateFlockCode(int $buildingId): string
    {
        $year = (int) date('Y');
        $farmId = (int) \App\Models\Building::query()->where('id', $buildingId)->value('farm_id');
        $sequence = app(NumberSequence::class);

        // Compteur par ferme (tous les bâtiments de la ferme partagent la numérotation)
        return $sequence->nextFormatted(
            prefix: 'BND-',
            key: 'flock',
            scopeId: $farmId,
            year: $year,
            pad: 3,
            scope: NumberSequence::SCOPE_FARM,
            seedUsing: fn (): int => (int) DB::table('flocks')
                ->join('buildings', 'buildings.id', '=', 'flocks.building_id')
                ->where('buildings.farm_id', $farmId)
                ->where('flocks.code', 'like', "BND-{$year}-%")
                ->selectRaw("MAX(CAST(SUBSTRING(flocks.code FROM '[0-9]+$') AS INTEGER)) AS max_num")
                ->value('max_num'),
        );
    }

    /**
     * Poids moyen d'un poussin d'un jour (g), utilisé quand aucune pesée n'a été
     * faite dans les trois premiers jours de vie.
     */
    public const DEFAULT_CHICK_WEIGHT_G = 40.0;

    /**
     * Poids de référence de départ : première pesée réalisée à J0–J3, sinon le poids
     * standard du poussin d'un jour. Retourne [poids en g, âge en jours].
     *
     * @return array{0: float, 1: int}
     */
    private function getInitialWeight(Flock $flock): array
    {
        $early = $flock->dailyRecords()
            ->whereNotNull('average_weight_g')
            ->where('age_days', '<=', 3)
            ->orderBy('record_date')
            ->first();

        if ($early) {
            return [(float) $early->average_weight_g, (int) $early->age_days];
        }

        return [self::DEFAULT_CHICK_WEIGHT_G, 0];
    }

    /**
     * Dernière pesée disponible.
     */
    private function getLastWeighing(Flock $flock): ?DailyRecord
    {
        return $flock->dailyRecords()
            ->whereNotNull('average_weight_g')
            ->orderByDesc('record_date')
            ->first();
    }

    /**
     * Gain Moyen Quotidien (g/j) = (poids moyen à la dernière pesée − poids de départ) / âge écoulé.
     */
    private function calculateGMQ(Flock $flock): ?float
    {
        $last = $this->getLastWeighing($flock);

        if (! $last) {
            return null;
        }

        [$initialWeight, $initialAge] = $this->getInitialWeight($flock);
        $days = (int) $last->age_days - $initialAge;

        if ($days <= 0) {
            return null;
        }

        return round(((float) $last->average_weight_g - $initialWeight) / $days, 2);
    }

    /**
     * Indice de Consommation (IC / FCR) = kg d'aliment consommé / kg de poids vif produit.
     *
     * Poids vif produit = poids moyen à la dernière pesée × (effectif présent + sujets vendus)
     *                     − poids de départ × effectif initial.
     * Hypothèse : les sujets vendus sont comptés au poids moyen de la dernière pesée
     * (à défaut d'une pesée à la vente). Les morts ne sont pas comptés dans le poids produit.
     */
    private function getFeedConversionRatio(Flock $flock): ?float
    {
        $totalFeed = (float) $flock->dailyRecords()->sum('feed_consumed_kg');
        $last = $this->getLastWeighing($flock);

        if (! $last || $totalFeed <= 0) {
            return null;
        }

        [$initialWeight] = $this->getInitialWeight($flock);

        $finalBirds = (int) $flock->quantity_current + (int) $flock->quantity_sold;
        $liveWeightProducedKg = (((float) $last->average_weight_g * $finalBirds) - ($initialWeight * (int) $flock->quantity_started)) / 1000;

        if ($liveWeightProducedKg <= 0) {
            return null;
        }

        return round($totalFeed / $liveWeightProducedKg, 2);
    }

    /**
     * Indice de Performance Européen (IPE / EPEF)
     * = viabilité (%) × poids vif moyen (kg) / (âge en jours × IC) × 100.
     */
    private function getEuropeanEfficiencyFactor(Flock $flock): ?float
    {
        $last = $this->getLastWeighing($flock);
        $ic = $this->getFeedConversionRatio($flock);
        $age = (int) $last?->age_days;

        if (! $last || ! $ic || $ic <= 0 || $age <= 0) {
            return null;
        }

        $weightKg = (float) $last->average_weight_g / 1000;

        return round(($flock->getViabilityRate() * $weightKg) / ($age * $ic) * 100, 1);
    }

    /**
     * Taux de ponte moyen (%) = Σ œufs collectés / Σ poules présentes par jour,
     * sur la période de ponte (depuis le premier relevé avec des œufs, jours à 0 œuf inclus).
     */
    private function getAverageLayingRate(Flock $flock): ?float
    {
        $firstLaying = $flock->dailyRecords()
            ->where('eggs_collected', '>', 0)
            ->orderBy('record_date')
            ->first();

        if (! $firstLaying) {
            return null;
        }

        $records = $flock->dailyRecords()
            ->whereDate('record_date', '>=', $firstLaying->record_date)
            ->get(['id', 'flock_id', 'eggs_collected', 'birds_present', 'mortality', 'culls']);

        $henDays = $records->sum(fn (DailyRecord $record): int => $record->getBirdsPresent());

        if ($henDays <= 0) {
            return null;
        }

        return round(($records->sum('eggs_collected') / $henDays) * 100, 2);
    }

    /**
     * Œufs par poule logée (HHP) = Σ œufs collectés / effectif initial.
     */
    private function getEggsPerHenHoused(Flock $flock): ?float
    {
        if ($flock->quantity_started <= 0) {
            return null;
        }

        return round($this->getTotalEggsCollected($flock) / $flock->quantity_started, 1);
    }

    /**
     * Total des œufs collectés sur toute la durée de la bande.
     */
    private function getTotalEggsCollected(Flock $flock): int
    {
        return (int) $flock->dailyRecords()->sum('eggs_collected');
    }

    /**
     * Infos sur la prochaine vaccination à venir.
     *
     * @return array{nom: string, type: string, date: string, dans_jours: int}|null
     */
    private function getNextVaccinationInfo(Flock $flock): ?array
    {
        $next = $flock->vaccinations()
            ->where('status', VaccinationStatus::Pending->value)
            ->where('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        if (! $next) {
            return null;
        }

        return [
            'nom' => $next->treatment_name,
            'type' => $next->treatment_type,
            'date' => $next->scheduled_date?->toDateString(),
            'dans_jours' => (int) now()->startOfDay()->diffInDays($next->scheduled_date, false),
        ];
    }

    /**
     * Construire le rapport de clôture.
     *
     * @return array<string, mixed>
     */
    private function buildCompletionReport(Flock $flock): array
    {
        $report = [
            'duree_jours' => $flock->getActualDurationInDays(),
            'effectif_initial' => $flock->quantity_started,
            'effectif_final' => $flock->quantity_current,
            'pertes_totales' => $flock->getLossCount(),
            'taux_mortalite' => round($flock->getMortalityRate(), 2),
            'cout_par_sujet' => $flock->getCostPerCurrentUnit(),

            'vaccinations_faites' => $flock->vaccinations()->where('status', VaccinationStatus::Done->value)->count(),
            'vaccinations_manquees' => $flock->vaccinations()
                ->whereIn('status', [VaccinationStatus::Skipped->value, VaccinationStatus::Delayed->value])
                ->count(),
        ];

        $kind = $flock->productionKind();

        if ($kind->tracksEggs()) {
            $report['oeufs_total'] = $this->getTotalEggsCollected($flock);
            $report['taux_ponte_moyen'] = $this->getAverageLayingRate($flock);
            $report['oeufs_par_poule_logee'] = $this->getEggsPerHenHoused($flock);
        }

        if ($kind->tracksWeight()) {
            $report['gmq'] = $this->calculateGMQ($flock);
            $report['ic'] = $this->getFeedConversionRatio($flock);
            $report['ipe'] = $this->getEuropeanEfficiencyFactor($flock);
        }

        return $report;
    }

    /**
     * Formater la description de clôture pour le journal de ferme.
     */
    private function formatCompletionDescription(Flock $flock, array $report): string
    {
        $duree = $report['duree_jours'] ?? '?';
        $desc = "Clôture de la bande {$flock->code} après {$duree} jours. ";
        $desc .= "Effectif final : {$report['effectif_final']}/{$report['effectif_initial']} sujets. ";
        $desc .= "Mortalité : {$report['taux_mortalite']}%. ";

        if (isset($report['oeufs_total'])) {
            $desc .= "Œufs produits : {$report['oeufs_total']}. ";
            if ($report['taux_ponte_moyen'] !== null) {
                $desc .= "Taux de ponte moyen : {$report['taux_ponte_moyen']}%. ";
            }
        }

        if (isset($report['gmq'])) {
            $gmq = $report['gmq'] ? "{$report['gmq']} g/j" : 'N/A';
            $ic = $report['ic'] ? "{$report['ic']}" : 'N/A';
            $desc .= "GMQ : {$gmq}. IC : {$ic}. ";
        }

        if ($report['vaccinations_manquees'] > 0) {
            $desc .= "{$report['vaccinations_manquees']} vaccination(s) non réalisée(s).";
        }

        return rtrim($desc);
    }
}
