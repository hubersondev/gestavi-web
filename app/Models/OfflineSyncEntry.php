<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saisie terrain reçue par la synchronisation hors-ligne, identifiée par son client_uuid.
 */
class OfflineSyncEntry extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organisation_id', 'user_id', 'client_uuid', 'type', 'payload',
        'status', 'message', 'subject_type', 'subject_id', 'recorded_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'recorded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
