<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace d'une alerte planifiée émise pour une organisation un jour donné
 * (garantit l'idempotence quotidienne des commandes gestavi:alert-*).
 */
class AlertDispatch extends Model
{
    protected $fillable = [
        'organisation_id',
        'alert_key',
        'dispatched_on',
        'items_count',
        'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_on' => 'date',
            'items_count' => 'integer',
            'recipients_count' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Vrai si l'alerte a déjà été émise aujourd'hui pour cette organisation.
     */
    public static function alreadySentToday(int $organisationId, string $alertKey): bool
    {
        return static::query()
            ->where('organisation_id', $organisationId)
            ->where('alert_key', $alertKey)
            ->whereDate('dispatched_on', today())
            ->exists();
    }

    public static function record(int $organisationId, string $alertKey, int $itemsCount, int $recipientsCount): self
    {
        return static::query()->firstOrCreate(
            ['organisation_id' => $organisationId, 'alert_key' => $alertKey, 'dispatched_on' => today()->toDateString()],
            ['items_count' => $itemsCount, 'recipients_count' => $recipientsCount],
        );
    }
}
