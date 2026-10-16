<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement d'une organisation à un plan sur une période. Le plan effectif de l'organisation
 * (`organisations.plan_id`) est synchronisé par SubscriptionService à chaque changement.
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /** Jours avant l'échéance auxquels les responsables sont prévenus. */
    public const ALERT_THRESHOLDS = [7, 3, 1];

    protected $fillable = [
        'organisation_id', 'plan_id', 'plan_name', 'status', 'billing_cycle',
        'price', 'currency', 'starts_at', 'ends_at', 'trial_ends_at',
        'cancelled_at', 'cancellation_reason', 'payment_method', 'payment_reference',
        'notes', 'alerted_thresholds', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_cycle' => BillingCycle::class,
            'payment_method' => PaymentMethod::class,
            'price' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'trial_ends_at' => 'date',
            'cancelled_at' => 'datetime',
            'alerted_thresholds' => 'array',
        ];
    }

    // ─── RELATIONS ────────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── SCOPES ───────────────────────────────────────────────────────────────

    /** Abonnements qui donnent accès au plan aujourd'hui. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->whereIn('status', array_map(fn (SubscriptionStatus $s) => $s->value, SubscriptionStatus::grantingAccess()))
            ->whereDate('ends_at', '>=', today()->toDateString());
    }

    /** Abonnements dont l'échéance tombe dans les $days prochains jours (aujourd'hui inclus). */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereIn('status', [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value])
            ->whereDate('ends_at', '>=', today()->toDateString())
            ->whereDate('ends_at', '<=', today()->addDays($days)->toDateString());
    }

    /** Abonnements encore ouverts dont l'échéance est passée. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', array_map(fn (SubscriptionStatus $s) => $s->value, SubscriptionStatus::grantingAccess()))
            ->whereDate('ends_at', '<', today()->toDateString());
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    public function isTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trial;
    }

    /** Donne-t-il accès au plan aujourd'hui ? */
    public function isCurrent(): bool
    {
        return in_array($this->status, SubscriptionStatus::grantingAccess(), true)
            && $this->ends_at !== null
            && $this->ends_at->greaterThanOrEqualTo(today());
    }

    public function isEnded(): bool
    {
        return $this->status === SubscriptionStatus::Expired || ! $this->isCurrent();
    }

    /** Jours restants jusqu'à l'échéance (0 si passée ou terminée). */
    public function daysRemaining(): int
    {
        if (! $this->isCurrent()) {
            return 0;
        }

        return max(0, (int) today()->diffInDays($this->ends_at, false));
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->isCurrent() && $this->daysRemaining() <= $days;
    }

    /** « 01/10/2026 → 31/10/2026 ». */
    public function periodLabel(): string
    {
        return $this->starts_at?->format('d/m/Y').' → '.$this->ends_at?->format('d/m/Y');
    }

    /** Libellé lisible : plan, cycle et statut (essai signalé). */
    public function summaryLabel(): string
    {
        $cycle = $this->isTrial() ? 'essai gratuit' : mb_strtolower($this->billing_cycle?->getLabel() ?? '');

        return trim($this->plan_name.' ('.$cycle.')');
    }

    /**
     * Mémorise qu'un seuil J-x a été notifié pour l'échéance en cours.
     */
    public function markThresholdAlerted(int $days): void
    {
        $thresholds = collect($this->alerted_thresholds ?? [])->push($days)->unique()->sort()->values()->all();
        $this->forceFill(['alerted_thresholds' => $thresholds])->save();
    }

    public function hasAlertedThreshold(int $days): bool
    {
        return in_array($days, $this->alerted_thresholds ?? [], true);
    }

    /**
     * Date de fin à appliquer quand on prolonge : à partir de l'échéance si elle est à venir,
     * sinon à partir d'aujourd'hui.
     */
    public function renewalBase(): CarbonInterface
    {
        return $this->ends_at !== null && $this->ends_at->greaterThan(today()) ? $this->ends_at : today();
    }
}
