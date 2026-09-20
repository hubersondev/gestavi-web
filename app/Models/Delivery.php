<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bon de livraison rattaché à une facture.
 */
class Delivery extends Model
{
    use BelongsToOrganisation, LogsAuditTrail;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'sale';

    protected $fillable = [
        'organisation_id', 'farm_id', 'sale_id', 'created_by',
        'delivery_number', 'delivery_date', 'status',
        'driver_name', 'vehicle', 'recipient_name', 'recipient_phone', 'delivery_address',
        'delivered_at', 'signature_note', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'delivery_date' => 'date',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Delivery $delivery): void {
            $delivery->created_by ??= auth()->id();
            $delivery->status ??= DeliveryStatus::Planned;

            if (empty($delivery->delivery_number) && $delivery->organisation_id) {
                $delivery->delivery_number = self::generateNumber($delivery->organisation_id);
            }
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [DeliveryStatus::Planned, DeliveryStatus::InTransit]);
    }

    public function isDelivered(): bool
    {
        return $this->status === DeliveryStatus::Delivered;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [DeliveryStatus::Planned, DeliveryStatus::InTransit], true);
    }

    public function markInTransit(): void
    {
        $this->update(['status' => DeliveryStatus::InTransit]);
    }

    public function markDelivered(?string $recipientName = null, ?string $signatureNote = null, mixed $deliveredAt = null): void
    {
        $this->update([
            'status' => DeliveryStatus::Delivered,
            'delivered_at' => $deliveredAt ?? now(),
            'recipient_name' => $recipientName ?? $this->recipient_name,
            'signature_note' => $signatureNote ?? $this->signature_note,
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => DeliveryStatus::Failed,
            'notes' => trim(($this->notes ? $this->notes."\n" : '').'Échec : '.$reason),
        ]);
    }

    /**
     * Numérotation continue BL-AAAA-00001 par organisation.
     */
    public static function generateNumber(int $organisationId): string
    {
        $year = now()->year;
        $sequence = app(NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'BL-',
            key: 'delivery',
            scopeId: $organisationId,
            year: $year,
            seedUsing: fn (): int => $sequence->maxSuffix('deliveries', 'delivery_number', "BL-{$year}-", ['organisation_id' => $organisationId]),
        );
    }
}
