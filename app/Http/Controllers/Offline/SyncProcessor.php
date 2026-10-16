<?php

namespace App\Http\Controllers\Offline;

use App\Enums\VaccinationStatus;
use App\Exceptions\StockException;
use App\Models\DailyRecord;
use App\Models\Flock;
use App\Models\OfflineSyncEntry;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use App\Models\Vaccination;
use App\Services\CurrentFarm;
use App\Services\FlockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Traite les saisies terrain reçues hors-ligne : idempotence par client_uuid,
 * cloisonnement par ferme, règles métier (effectif, date), journalisation.
 */
class SyncProcessor
{
    public function __construct(
        private readonly CurrentFarm $currentFarm,
        private readonly FlockService $flockService,
    ) {}

    /**
     * @param  array<int, array{client_uuid: string, type: string, payload: array<string, mixed>, recorded_at?: string|null}>  $entries
     * @return list<array{client_uuid: string, status: string, message: string|null, id: int|null}>
     */
    public function process(Organisation $organisation, User $user, OrganisationUser $membership, array $entries): array
    {
        $results = [];

        foreach ($entries as $entry) {
            $existing = OfflineSyncEntry::query()->where('client_uuid', $entry['client_uuid'])->first();

            if ($existing) {
                // Déjà reçue : on renvoie le même verdict sans retraiter
                $results[] = $this->result($existing->client_uuid, OfflineSyncEntry::STATUS_DUPLICATE, $existing->message ?? 'Saisie déjà synchronisée.', $existing->subject_id);

                continue;
            }

            [$status, $message, $subjectType, $subjectId] = match ($entry['type']) {
                'daily_record' => $this->processDailyRecord($organisation, $user, $membership, $entry['payload']),
                'vaccination_done' => $this->processVaccinationDone($organisation, $user, $membership, $entry['payload']),
                default => [OfflineSyncEntry::STATUS_REJECTED, 'Type de saisie inconnu.', null, null],
            };

            OfflineSyncEntry::query()->create([
                'organisation_id' => $organisation->id,
                'user_id' => $user->id,
                'client_uuid' => $entry['client_uuid'],
                'type' => $entry['type'],
                'payload' => $entry['payload'],
                'status' => $status,
                'message' => $message,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'recorded_at' => isset($entry['recorded_at']) ? Carbon::parse($entry['recorded_at']) : null,
                'processed_at' => now(),
            ]);

            $results[] = $this->result($entry['client_uuid'], $status, $message, $subjectId);
        }

        return $results;
    }

    /**
     * Bandes actives accessibles au membre (cloisonnement par ferme).
     */
    public function accessibleFlocks(Organisation $organisation, OrganisationUser $membership)
    {
        return $this->currentFarm->restrictToFarms(
            Flock::query()->where('flocks.organisation_id', $organisation->id)->active(),
            $membership,
            'flocks.farm_id',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string|null, 2: string|null, 3: int|null}
     */
    private function processDailyRecord(Organisation $organisation, User $user, OrganisationUser $membership, array $payload): array
    {
        if (! $user->hasOrganisationPermission($organisation, 'daily_records.create')) {
            return [OfflineSyncEntry::STATUS_REJECTED, 'Vous n\'avez pas la permission de saisir des relevés.', null, null];
        }

        try {
            $data = Validator::make($payload, [
                'flock_id' => ['required', 'integer'],
                'record_date' => ['required', 'date', 'before_or_equal:today'],
                'mortality' => ['nullable', 'integer', 'min:0'],
                'culls' => ['nullable', 'integer', 'min:0'],
                'thefts' => ['nullable', 'integer', 'min:0'],
                'eggs_collected' => ['nullable', 'integer', 'min:0'],
                'eggs_broken' => ['nullable', 'integer', 'min:0'],
                'feed_consumed_kg' => ['nullable', 'numeric', 'min:0'],
                'water_consumed_l' => ['nullable', 'numeric', 'min:0'],
                'average_weight_g' => ['nullable', 'numeric', 'min:0'],
                'birds_weighed' => ['nullable', 'integer', 'min:0'],
                'temperature_min' => ['nullable', 'numeric'],
                'temperature_max' => ['nullable', 'numeric'],
                'humidity_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'notes' => ['nullable', 'string', 'max:2000'],
                // Le mobile a pré-rempli un relevé existant et renvoie les totaux du jour
                'merge' => ['nullable', 'boolean'],
            ], [
                'record_date.before_or_equal' => 'La date du relevé ne peut pas être dans le futur.',
            ])->validate();
        } catch (ValidationException $e) {
            return [OfflineSyncEntry::STATUS_REJECTED, collect($e->errors())->flatten()->implode(' '), null, null];
        }

        $flock = $this->accessibleFlocks($organisation, $membership)->whereKey($data['flock_id'])->first();

        if (! $flock) {
            return [OfflineSyncEntry::STATUS_REJECTED, 'Bande introuvable ou hors de vos fermes.', null, null];
        }

        $recordDate = Carbon::parse($data['record_date'])->toDateString();
        $merge = (bool) ($data['merge'] ?? false);
        unset($data['merge']);

        $existing = DailyRecord::query()->where('flock_id', $flock->id)->whereDate('record_date', $recordDate)->first();

        if ($existing && ! $merge) {
            return [OfflineSyncEntry::STATUS_DUPLICATE, "Un relevé existe déjà pour la bande {$flock->code} au {$recordDate} : la saisie hors-ligne n'a pas écrasé la version en ligne.", DailyRecord::class, null];
        }

        if ($existing) {
            return $this->completeDailyRecord($existing, $flock, $user, $data, $recordDate);
        }

        try {
            $record = DB::transaction(fn (): DailyRecord => $this->flockService->recordDailyData($flock, array_merge(
                array_filter($data, fn ($value) => $value !== null),
                ['record_date' => $recordDate, 'recorded_by' => $user->id],
            )));
        } catch (\InvalidArgumentException|StockException $e) {
            // Règle métier (pertes > effectif, stock d'aliment insuffisant…) : saisie refusée, message explicite
            return [OfflineSyncEntry::STATUS_REJECTED, $e->getMessage(), null, null];
        }

        return [OfflineSyncEntry::STATUS_CREATED, "Relevé du {$recordDate} enregistré pour la bande {$flock->code}.", DailyRecord::class, $record->id];
    }

    /**
     * Le relevé du jour existe déjà (saisi sur le web ou par une précédente synchronisation) et le
     * mobile l'avait pré-rempli : ses totaux remplacent les valeurs en place. Les règles métier
     * s'appliquent comme à une correction (pertes dans l'effectif, aliment régularisé sur le stock).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string|null, 2: string|null, 3: int|null}
     */
    private function completeDailyRecord(DailyRecord $existing, Flock $flock, User $user, array $data, string $recordDate): array
    {
        $values = array_filter($data, fn ($value) => $value !== null);
        unset($values['flock_id'], $values['record_date']);

        try {
            DB::transaction(function () use ($existing, $values, $user): void {
                $previousKg = (float) $existing->feed_consumed_kg;

                $existing->update($values + ['recorded_by' => $user->id]);
                $existing->refresh();

                if ($existing->feed_movement_id) {
                    $this->flockService->adjustFeedConsumption($existing, $previousKg, (float) $existing->feed_consumed_kg);
                }
            });
        } catch (\InvalidArgumentException|StockException $e) {
            return [OfflineSyncEntry::STATUS_REJECTED, $e->getMessage(), null, null];
        }

        return [OfflineSyncEntry::STATUS_CREATED, "Relevé du {$recordDate} complété pour la bande {$flock->code}.", DailyRecord::class, $existing->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string|null, 2: string|null, 3: int|null}
     */
    private function processVaccinationDone(Organisation $organisation, User $user, OrganisationUser $membership, array $payload): array
    {
        if (! $user->hasOrganisationPermission($organisation, 'vaccinations.mark_done')) {
            return [OfflineSyncEntry::STATUS_REJECTED, 'Vous n\'avez pas la permission de valider des vaccinations.', null, null];
        }

        try {
            $data = Validator::make($payload, [
                'vaccination_id' => ['required', 'integer'],
                'administered_date' => ['required', 'date', 'before_or_equal:today'],
                'birds_treated' => ['nullable', 'integer', 'min:1'],
                'observations' => ['nullable', 'string', 'max:2000'],
            ], [
                'administered_date.before_or_equal' => 'La date d\'administration ne peut pas être dans le futur.',
            ])->validate();
        } catch (ValidationException $e) {
            return [OfflineSyncEntry::STATUS_REJECTED, collect($e->errors())->flatten()->implode(' '), null, null];
        }

        $flockIds = $this->accessibleFlocks($organisation, $membership)->pluck('flocks.id');

        $vaccination = Vaccination::query()
            ->whereKey($data['vaccination_id'])
            ->whereIn('flock_id', $flockIds)
            ->first();

        if (! $vaccination) {
            return [OfflineSyncEntry::STATUS_REJECTED, 'Vaccination introuvable ou hors de vos fermes.', null, null];
        }

        if ($vaccination->status === VaccinationStatus::Done) {
            return [OfflineSyncEntry::STATUS_DUPLICATE, "La vaccination « {$vaccination->treatment_name} » était déjà marquée effectuée.", Vaccination::class, $vaccination->id];
        }

        $vaccination->markAsDone([
            'administered_date' => Carbon::parse($data['administered_date'])->toDateString(),
            'birds_treated' => $data['birds_treated'] ?? $vaccination->flock?->quantity_current,
            'observations' => $data['observations'] ?? null,
            'administered_by' => $user->id,
        ]);

        return [OfflineSyncEntry::STATUS_CREATED, "Vaccination « {$vaccination->treatment_name} » enregistrée.", Vaccination::class, $vaccination->id];
    }

    /**
     * @return array{client_uuid: string, status: string, message: string|null, id: int|null}
     */
    private function result(string $clientUuid, string $status, ?string $message, ?int $id): array
    {
        return ['client_uuid' => $clientUuid, 'status' => $status, 'message' => $message, 'id' => $id];
    }
}
