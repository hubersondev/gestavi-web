<?php

namespace App\Models;

use App\Enums\MobileMoneyOperator;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use App\Services\MobileMoney\Direction;
use App\Services\MobileMoney\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encaissement mobile money reçu par webhook, à rapprocher d'une facture.
 */
class MobileMoneyTransaction extends Model
{
    use BelongsToOrganisation, LogsAuditTrail;
    use HasFactory;

    /** Parent métier dont héritent organisation_id et farm_id (compte marchand). */
    protected static string $organisationParentRelation = 'account';

    protected $fillable = [
        'organisation_id', 'farm_id', 'account_id',
        'operator', 'external_id', 'direction', 'amount', 'currency',
        'payer_phone', 'payer_name', 'reference', 'occurred_at',
        'status', 'payment_id', 'matched_by', 'matched_at', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'operator' => MobileMoneyOperator::class,
            'direction' => Direction::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'matched_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::Received->value);
    }

    public function scopeMatched(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::Matched->value);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isReceived(): bool
    {
        return $this->status === TransactionStatus::Received;
    }

    public function isMatched(): bool
    {
        return $this->status === TransactionStatus::Matched;
    }

    public function isIgnored(): bool
    {
        return $this->status === TransactionStatus::Ignored;
    }
}
