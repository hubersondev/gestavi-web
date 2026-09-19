<?php

namespace App\Models\Concerns;

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
}
