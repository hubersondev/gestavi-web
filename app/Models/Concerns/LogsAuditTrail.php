<?php

namespace App\Models\Concerns;

use App\Services\Impersonation;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Piste d'audit commune aux enregistrements sensibles (trésorerie, stocks,
 * production, paie) : chaque création, modification (valeurs avant/après)
 * et suppression est journalisée avec son auteur dans `activity_log`.
 */
trait LogsAuditTrail
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('gestavi')
            ->logAll()
            ->logExcept(['updated_at', 'created_at', 'remember_token', 'password'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Chaque entrée porte l'organisation du sujet : le journal se consulte par organisation.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->organisation_id = $this->getAttribute('organisation_id')
            ?? ($activity->getExtraProperty('attributes', [])['organisation_id'] ?? null)
            ?? ($activity->getExtraProperty('old', [])['organisation_id'] ?? null);

        // Session de support : l'écriture est faite au nom de l'utilisateur, l'administrateur est tracé
        if ($impersonator = session(Impersonation::SESSION_KEY)) {
            $activity->properties = $activity->properties->put('impersonator_id', (int) $impersonator);
        }
    }
}
