<?php

namespace App\Models;

use App\Enums\LedgerAnchorReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ancrage de la tête de chaîne du ledger d'une organisation : hash de la dernière
 * écriture et nombre d'écritures à un instant donné. Append-only (trigger en base) ;
 * une copie est écrite hors base par LedgerService::anchor().
 */
class LedgerAnchor extends Model
{
    use HasFactory;

    public const null UPDATED_AT = null;

    protected $fillable = [
        'organisation_id', 'head_entry_id', 'head_hash', 'entry_count', 'reason', 'is_valid', 'anchored_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'reason' => LedgerAnchorReason::class,
            'is_valid' => 'boolean',
            'entry_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function headEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'head_entry_id');
    }

    public function anchoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anchored_by');
    }
}
