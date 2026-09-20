<?php

namespace App\Services\Sms;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des SMS émis (table sms_messages) : destinataire, pilote, statut, identifiant opérateur.
 */
class SmsMessage extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'sms_messages';

    protected $fillable = [
        'organisation_id', 'user_id', 'to', 'message', 'driver', 'status', 'provider_id', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
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
