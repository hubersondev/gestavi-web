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
    public function completeFlock(Flock $flock, ?string $notes = null): array
    {
        if (! $flock->isActive()) {
            throw new \LogicException('Seule une bande en cours peut être clôturée.');
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

        $indicators = [
            'age_jours' => $flock->getCurrentAgeInDays(),
            'age_semaines' => $flock->getCurrentAgeInWeeks(),
            'phase_actuelle' => $flock->getCurrentPhase()?->name,

            'effectif_initial' => $flock->quantity_started,
            'effectif_actuel' => $flock->quantity_current,
            'pertes_totales' => $flock->getLossCount(),
            'taux_mortalite' => round($flock->getMortalityRate(), 2),

            'cout_par_sujet' => $flock->getCostPerCurrentUnit(),

            'vaccinations_a_jour' => $flock->vaccinations()->overdue()->doesntExist(),
            'vaccinations_en_retard' => $flock->vaccinations()->overdue()->count(),
            'prochaine_vaccination' => $this->getNextVaccinationInfo($flock),
        ];

        // Indicateurs depuis le dernier relevé quotidien
        if ($lastRecord) {
            $indicators['derniere_saisie'] = $lastRecord->record_date?->format('d/m/Y');
            $indicators['aliment_par_sujet_g'] = $lastRecord->getFeedPerBird();
            $indicators['mortalite_dernier_jour'] = $lastRecord->mortality;
        }

        // Indicateurs pondeuse
        $chickenTypeName = strtolower($flock->chickenType?->name ?? '');
        if (str_contains($chickenTypeName, 'ponde')) {
            $indicators['oeufs_jour'] = $lastRecord?->eggs_collected;
            $indicators['taux_ponte_jour'] = $lastRecord?->getLayingRate();
            $indicators['taux_ponte_moyen'] = $this->getAverageLayingRate($flock);
            $indicators['oeufs_total'] = $this->getTotalEggsCollected($flock);
        }

        // Indicateurs chair
        if (str_contains($chickenTypeName, 'chair')) {
            $indicators['poids_moyen'] = $lastRecord?->average_weight_g;
            $indicators['gmq'] = $this->calculateGMQ($flock);
            $indicators['ic'] = $this->getFeedConversionRatio($flock);
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
        $year = date('Y');
        $prefix = "BND-{$year}-";

        // Chercher dans tous les bâtiments de la même ferme
        $last = Flock::whereHas('building', fn ($q) => $q->where(
            'farm_id',
            \App\Models\Building::where('id', $buildingId)->value('farm_id')
        ))
            ->where('code', 'like', "{$prefix}%")
            ->orderByDesc('code')
            ->first();

        $next = 1;
        if ($last) {
            $parts = explode('-', $last->code);
            $next = ((int) end($parts)) + 1;
        }

        return sprintf('BND-%s-%03d', $year, $next);
    }

    /**
     * Calculer le Gain Moyen Quotidien en grammes (chair uniquement).
     */
    private function calculateGMQ(Flock $flock): ?float
    {
        $first = $flock->dailyRecords()
            ->whereNotNull('average_weight_g')
            ->orderBy('record_date')
            ->first();

        $last = $flock->dailyRecords()
            ->whereNotNull('average_weight_g')
            ->orderByDesc('record_date')
            ->first();

        if (! $first || ! $last || $first->id === $last->id) {
            return null;
        }

        $days = (int) $first->record_date->diffInDays($last->record_date);

        if ($days === 0) {
            return null;
        }

        return round(((float) $last->average_weight_g - (float) $first->average_weight_g) / $days, 2);
    }

    /**
     * Calculer l'Indice de Consommation (IC = kg aliment / kg poids vif gagné).
     */
    private function getFeedConversionRatio(Flock $flock): ?float
    {
        $totalFeed = $flock->dailyRecords()->sum('feed_consumed_kg');

        $firstRecord = $flock->dailyRecords()->whereNotNull('average_weight_g')->orderBy('record_date')->first();
        $lastRecord = $flock->dailyRecords()->whereNotNull('average_weight_g')->orderByDesc('record_date')->first();

        if (! $firstRecord || ! $lastRecord || $firstRecord->id === $lastRecord->id || $totalFeed <= 0) {
            return null;
        }

        $weightGainKg = ((float) $lastRecord->average_weight_g - (float) $firstRecord->average_weight_g)
            * $flock->quantity_current
            / 1000;

        if ($weightGainKg <= 0) {
            return null;
        }

        return round((float) $totalFeed / $weightGainKg, 2);
    }

    /**
     * Taux de ponte moyen sur toute la durée de la bande (pondeuse).
     */
    private function getAverageLayingRate(Flock $flock): ?float
    {
        $records = $flock->dailyRecords()
            ->whereNotNull('eggs_collected')
            ->where('eggs_collected', '>', 0)
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $rates = $records->map(fn ($r) => $r->getLayingRate())->filter();

        return $rates->isNotEmpty() ? round($rates->avg(), 2) : null;
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

        $chickenTypeName = strtolower($flock->chickenType?->name ?? '');

        if (str_contains($chickenTypeName, 'ponde')) {
            $report['oeufs_total'] = $this->getTotalEggsCollected($flock);
            $report['taux_ponte_moyen'] = $this->getAverageLayingRate($flock);
        }

        if (str_contains($chickenTypeName, 'chair')) {
            $report['gmq'] = $this->calculateGMQ($flock);
            $report['ic'] = $this->getFeedConversionRatio($flock);
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
