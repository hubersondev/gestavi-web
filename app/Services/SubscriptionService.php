<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\OrganisationStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Cycle de vie des abonnements : souscription (essai ou payante), renouvellement, changement de
 * plan sous contrainte des limites, annulation, expirations quotidiennes, et alertes d'échéance
 * (J-7, J-3, J-1) aux responsables de l'organisation.
 *
 * Un abonnement expiré n'enlève rien en silence : l'organisation garde son plan pendant un délai
 * de grâce (config `subscriptions.grace_days`), puis passe en lecture seule jusqu'à régularisation ;
 * une souscription ou un renouvellement la réactive automatiquement.
 *
 * Le plan effectif de l'organisation (`organisations.plan_id`) suit toujours l'abonnement courant.
 */
class SubscriptionService
{
    /** Début du motif de lecture seule posé automatiquement (permet de la lever à la régularisation). */
    public const READ_ONLY_REASON_PREFIX = 'Abonnement expiré';

    /**
     * Souscrit un plan : essai gratuit si $trialDays > 0, sinon période payée selon le cycle.
     * L'abonnement courant éventuel est clos (annulé à la date du jour) et remplacé.
     *
     * @param  array{payment_method?: ?string, payment_reference?: ?string, notes?: ?string, price?: ?float}  $payment
     */
    public function start(
        Organisation $organisation,
        Plan $plan,
        BillingCycle $cycle = BillingCycle::Monthly,
        ?int $trialDays = null,
        array $payment = [],
        ?User $by = null,
        ?CarbonInterface $startsAt = null,
    ): Subscription {
        $this->assertWithinLimits($organisation, $plan);

        return DB::transaction(function () use ($organisation, $plan, $cycle, $trialDays, $payment, $by, $startsAt): Subscription {
            $starts = ($startsAt ?? today())->toImmutable()->startOfDay();
            $isTrial = ($trialDays ?? 0) > 0;

            $current = $this->currentFor($organisation);

            // L'abonnement remplacé passe à l'historique : clos la veille du nouveau (au plus tôt à son début)
            if ($current) {
                $current->forceFill([
                    'status' => SubscriptionStatus::Expired,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Remplacé par un abonnement '.$plan->name,
                    'ends_at' => $starts->subDay()->lessThan($current->starts_at) ? $current->starts_at->toDateString() : $starts->subDay()->toDateString(),
                ])->save();
            }

            $subscription = Subscription::query()->create([
                'organisation_id' => $organisation->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'status' => $isTrial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
                'billing_cycle' => $cycle,
                'price' => $isTrial ? 0 : ($payment['price'] ?? $cycle->priceFor($plan)),
                'currency' => 'XOF',
                'starts_at' => $starts->toDateString(),
                'ends_at' => ($isTrial ? $starts->addDays($trialDays) : $starts->addMonths($cycle->months())->subDay())->toDateString(),
                'trial_ends_at' => $isTrial ? $starts->addDays($trialDays)->toDateString() : null,
                'payment_method' => $isTrial ? null : ($payment['payment_method'] ?? null),
                'payment_reference' => $isTrial ? null : ($payment['payment_reference'] ?? null),
                'notes' => $payment['notes'] ?? null,
                'created_by' => $by?->id,
            ]);

            $this->syncPlan($organisation, $plan);
            $this->liftAutomaticReadOnly($organisation);

            if (! $isTrial && (float) $subscription->price > 0) {
                $this->recordPayment($subscription, (float) $subscription->price, $payment, $by, $starts);
            }

            return $subscription;
        });
    }

    /**
     * Prolonge l'abonnement d'une période (à partir de l'échéance si elle est à venir, sinon
     * d'aujourd'hui). Un essai renouvelé devient un abonnement payé.
     *
     * @param  array{payment_method?: ?string, payment_reference?: ?string, price?: ?float}  $payment
     */
    public function renew(Subscription $subscription, ?BillingCycle $cycle = null, array $payment = [], ?User $by = null): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Expired && ! $this->isLatestFor($subscription)) {
            throw SubscriptionException::notRenewable($subscription->status->getLabel());
        }

        $plan = $subscription->plan ?? throw SubscriptionException::notRenewable('sans plan');
        $cycle ??= $subscription->billing_cycle ?? BillingCycle::Monthly;

        return DB::transaction(function () use ($subscription, $plan, $cycle, $payment, $by): Subscription {
            $base = $subscription->renewalBase()->toImmutable();
            $wasEnded = $subscription->isEnded();

            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'billing_cycle' => $cycle,
                'price' => $payment['price'] ?? $cycle->priceFor($plan),
                'starts_at' => $wasEnded ? $base->toDateString() : $subscription->starts_at->toDateString(),
                'ends_at' => $base->addMonths($cycle->months())->subDay()->toDateString(),
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'payment_method' => $payment['payment_method'] ?? $subscription->payment_method?->value,
                'payment_reference' => $payment['payment_reference'] ?? $subscription->payment_reference,
                'alerted_thresholds' => [],
            ])->save();

            $this->syncPlan($subscription->organisation, $plan);
            $this->liftAutomaticReadOnly($subscription->organisation);
            $subscription->refresh();

            if ((float) $subscription->price > 0) {
                $payment = $this->recordPayment($subscription, (float) $subscription->price, $payment, $by);
                $payment->forceFill(['period_from' => $base->toDateString()])->save();
            }

            return $subscription;
        });
    }

    /**
     * Change le plan d'un abonnement courant, immédiatement, si l'organisation respecte les
     * limites du nouveau plan (fermes, utilisateurs). Le prix de la période est recalculé.
     */
    public function changePlan(Subscription $subscription, Plan $plan, ?User $by = null): Subscription
    {
        if (! $subscription->isCurrent()) {
            throw SubscriptionException::alreadyEnded();
        }

        $this->assertWithinLimits($subscription->organisation, $plan);

        return DB::transaction(function () use ($subscription, $plan): Subscription {
            $subscription->forceFill([
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'price' => $subscription->isTrial() ? 0 : ($subscription->billing_cycle ?? BillingCycle::Monthly)->priceFor($plan),
            ])->save();

            $this->syncPlan($subscription->organisation, $plan);
            $this->liftAutomaticReadOnly($subscription->organisation);

            return $subscription->refresh();
        });
    }

    /**
     * Annule l'abonnement. Par défaut l'accès au plan est maintenu jusqu'à l'échéance ;
     * `$immediately` clôt l'abonnement et passe l'organisation en lecture seule tout de suite.
     */
    public function cancel(Subscription $subscription, ?string $reason = null, bool $immediately = false, ?User $by = null): Subscription
    {
        if ($subscription->isEnded() || $subscription->status === SubscriptionStatus::Cancelled) {
            throw SubscriptionException::alreadyEnded();
        }

        return DB::transaction(function () use ($subscription, $reason, $immediately): Subscription {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'ends_at' => $immediately ? today()->subDay()->toDateString() : $subscription->ends_at->toDateString(),
            ])->save();

            if ($immediately) {
                $this->expire($subscription, readOnlyNow: true);
            }

            return $subscription->refresh();
        });
    }

    /**
     * Traitement quotidien : expire les abonnements échus (plan conservé, notification avec le délai
     * de grâce), passe en lecture seule les organisations dont le délai de grâce est écoulé sans
     * régularisation, puis prévient les responsables des échéances à J-7, J-3 et J-1.
     *
     * @return array{expired: int, read_only: int, alerts: int}
     */
    public function processExpirations(): array
    {
        $expired = 0;
        $alerts = 0;

        Subscription::query()->overdue()->with('organisation')->chunkById(100, function ($chunk) use (&$expired): void {
            foreach ($chunk as $subscription) {
                $this->expire($subscription);
                $expired++;
            }
        });

        Subscription::query()->expiringWithin(max(Subscription::ALERT_THRESHOLDS))->with('organisation')->chunkById(100, function ($chunk) use (&$alerts): void {
            foreach ($chunk as $subscription) {
                $days = $subscription->daysRemaining();
                $threshold = collect(Subscription::ALERT_THRESHOLDS)->sort()->first(fn (int $t): bool => $days <= $t);

                if ($threshold === null || $subscription->hasAlertedThreshold($threshold)) {
                    continue;
                }

                $this->notifyExpiring($subscription, $days);
                $subscription->markThresholdAlerted($threshold);
                $alerts++;
            }
        });

        return ['expired' => $expired, 'read_only' => $this->enforceGracePeriods(), 'alerts' => $alerts];
    }

    public static function graceDays(): int
    {
        $override = PlatformSettings::get('subscription_grace_days');

        return max(0, (int) ($override ?? config('subscriptions.grace_days', 7)));
    }

    /**
     * Enregistre un encaissement (facture numérotée) pour la période courante de l'abonnement.
     *
     * @param  array{payment_method?: ?string, payment_reference?: ?string, notes?: ?string}  $payment
     */
    public function recordPayment(Subscription $subscription, float $amount, array $payment = [], ?User $by = null, ?CarbonInterface $paidAt = null): SubscriptionPayment
    {
        return SubscriptionPayment::query()->create([
            'organisation_id' => $subscription->organisation_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => SubscriptionPayment::nextInvoiceNumber(),
            'plan_name' => $subscription->plan_name,
            'billing_cycle' => $subscription->billing_cycle?->value ?? BillingCycle::Monthly->value,
            'period_from' => $subscription->starts_at->toDateString(),
            'period_to' => $subscription->ends_at->toDateString(),
            'amount' => $amount,
            'currency' => $subscription->currency ?? 'XOF',
            'paid_at' => ($paidAt ?? today())->toDateString(),
            'payment_method' => $payment['payment_method'] ?? $subscription->payment_method?->value,
            'payment_reference' => $payment['payment_reference'] ?? $subscription->payment_reference,
            'notes' => $payment['notes'] ?? null,
            'created_by' => $by?->id,
        ]);
    }

    /**
     * Situation d'une organisation dont le dernier abonnement est expiré et non remplacé :
     * date de passage en lecture seule et jours restants. Null si rien n'est en cours.
     *
     * @return array{subscription: Subscription, read_only_at: CarbonInterface, days_left: int}|null
     */
    public function graceInfo(Organisation $organisation): ?array
    {
        if ($this->currentFor($organisation)) {
            return null;
        }

        $latest = Subscription::query()
            ->where('organisation_id', $organisation->id)
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->first();

        if (! $latest || $latest->status !== SubscriptionStatus::Expired) {
            return null;
        }

        $readOnlyAt = $latest->ends_at->toImmutable()->addDays(self::graceDays() + 1)->startOfDay();

        return [
            'subscription' => $latest,
            'read_only_at' => $readOnlyAt,
            'days_left' => max(0, (int) today()->diffInDays($readOnlyAt, false)),
        ];
    }

    /**
     * Organisations actives dont le dernier abonnement est expiré depuis plus que le délai de grâce,
     * sans abonnement courant : passage en lecture seule et notification. Retourne le nombre traité.
     */
    private function enforceGracePeriods(): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Expired->value)
            ->whereDate('ends_at', '<=', today()->subDays(self::graceDays())->toDateString())
            ->whereHas('organisation', fn ($q) => $q->where('status', OrganisationStatus::Active->value))
            ->with('organisation')
            ->chunkById(100, function ($chunk) use (&$count): void {
                foreach ($chunk as $subscription) {
                    $organisation = $subscription->organisation;

                    if (! $organisation || ! $this->isLatestFor($subscription) || $this->currentFor($organisation)) {
                        continue;
                    }

                    $this->applyReadOnly($organisation, $subscription);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Passe l'organisation en lecture seule pour défaut de renouvellement et prévient les responsables.
     */
    private function applyReadOnly(Organisation $organisation, Subscription $subscription): void
    {
        if ($organisation->isSuspended()) {
            return;
        }

        $organisation->setStatus(OrganisationStatus::ReadOnly, sprintf(
            '%s le %s et non renouvelé : consultation possible, saisie suspendue jusqu\'à régularisation.',
            self::READ_ONLY_REASON_PREFIX,
            $subscription->ends_at->format('d/m/Y'),
        ));

        $recipients = AlertRecipients::globalAccess($organisation->id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new OrganisationAlertNotification(
            title: 'Organisation en lecture seule : abonnement non renouvelé',
            lines: [
                '• '.ucfirst($subscription->summaryLabel()).' expiré le '.$subscription->ends_at->format('d/m/Y').'.',
                '• Vos données restent consultables ; toute saisie est suspendue jusqu\'au renouvellement.',
            ],
            alertKey: 'subscription_read_only',
            organisationId: $organisation->id,
            status: 'danger',
            icon: 'heroicon-o-lock-closed',
            actionLabel: 'Voir les plans',
            actionUrl: $this->upgradeUrl($organisation),
        ));
    }

    /**
     * Lève une lecture seule posée automatiquement pour défaut de renouvellement (jamais une
     * lecture seule décidée par la plateforme).
     */
    private function liftAutomaticReadOnly(Organisation $organisation): void
    {
        $organisation->refresh();

        if ($organisation->isReadOnly() && str_starts_with((string) $organisation->status_reason, self::READ_ONLY_REASON_PREFIX)) {
            $organisation->setStatus(OrganisationStatus::Active);
        }
    }

    /**
     * Utilisation de l'organisation face aux limites de son plan.
     *
     * @return array{farms_used: int, farms_max: ?int, users_used: int, users_max: ?int}
     */
    public function usage(Organisation $organisation, ?Plan $plan = null): array
    {
        $plan ??= $organisation->plan;

        return [
            'farms_used' => $organisation->farms()->count(),
            'farms_max' => $plan?->max_farms,
            'users_used' => $organisation->activeMembers()->count(),
            'users_max' => $plan?->max_users,
        ];
    }

    /** Abonnement donnant accès au plan aujourd'hui, s'il existe. */
    public function currentFor(Organisation $organisation): ?Subscription
    {
        return Subscription::query()
            ->where('organisation_id', $organisation->id)
            ->current()
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->first();
    }

    // ─── PRIVÉ ────────────────────────────────────────────────────────────────

    private function assertWithinLimits(Organisation $organisation, Plan $plan): void
    {
        $usage = $this->usage($organisation, $plan);

        if ($plan->max_farms !== null && $usage['farms_used'] > (int) $plan->max_farms) {
            throw SubscriptionException::limitExceeded('ferme(s)', $usage['farms_used'], (int) $plan->max_farms, $plan->name);
        }

        if ($plan->max_users !== null && $usage['users_used'] > (int) $plan->max_users) {
            throw SubscriptionException::limitExceeded('utilisateur(s)', $usage['users_used'], (int) $plan->max_users, $plan->name);
        }
    }

    private function syncPlan(Organisation $organisation, ?Plan $plan): void
    {
        if ($organisation->plan_id !== $plan?->id) {
            $organisation->forceFill(['plan_id' => $plan?->id])->save();
        }
    }

    /**
     * Marque l'abonnement expiré. S'il est remplacé, le plan suit le remplaçant ; sinon le plan
     * est conservé et les responsables sont prévenus du délai de grâce avant la lecture seule
     * (`$readOnlyNow` l'applique immédiatement, cas de l'annulation immédiate).
     */
    private function expire(Subscription $subscription, bool $readOnlyNow = false): void
    {
        $subscription->forceFill(['status' => SubscriptionStatus::Expired])->save();

        $organisation = $subscription->organisation;
        $replacement = $this->currentFor($organisation);

        if ($replacement) {
            $this->syncPlan($organisation, $replacement->plan);

            return;
        }

        if ($readOnlyNow) {
            $this->applyReadOnly($organisation, $subscription);

            return;
        }

        $recipients = AlertRecipients::globalAccess($organisation->id);

        if ($recipients->isEmpty()) {
            return;
        }

        $grace = self::graceDays();
        $readOnlyAt = $subscription->ends_at->toImmutable()->addDays($grace + 1);

        Notification::send($recipients, new OrganisationAlertNotification(
            title: 'Abonnement '.$subscription->plan_name.' expiré',
            lines: [
                '• Échéance du '.$subscription->ends_at->format('d/m/Y').' dépassée ; vos fonctionnalités sont conservées pendant '.$grace.' jour(s).',
                '• Sans renouvellement, l\'organisation passera en lecture seule le '.$readOnlyAt->format('d/m/Y').'.',
            ],
            alertKey: 'subscription_expired',
            organisationId: $organisation->id,
            status: 'danger',
            icon: 'heroicon-o-credit-card',
            actionLabel: 'Voir les plans',
            actionUrl: $this->upgradeUrl($organisation),
        ));
    }

    private function notifyExpiring(Subscription $subscription, int $days): void
    {
        $organisation = $subscription->organisation;
        $recipients = AlertRecipients::globalAccess($organisation->id);

        if ($recipients->isEmpty()) {
            return;
        }

        $when = match (true) {
            $days <= 0 => 'aujourd\'hui',
            $days === 1 => 'demain',
            default => "dans {$days} jours",
        };

        Notification::send($recipients, new OrganisationAlertNotification(
            title: ($subscription->isTrial() ? 'Fin d\'essai ' : 'Échéance d\'abonnement ').$when,
            lines: [
                '• '.ucfirst($subscription->summaryLabel()).' jusqu\'au '.$subscription->ends_at->format('d/m/Y').'.',
                '• '.($subscription->isTrial()
                    ? 'Souscrivez un plan pour conserver vos fonctionnalités après l\'essai.'
                    : 'Renouvelez pour éviter le retour au plan gratuit.'),
            ],
            alertKey: 'subscription_expiry',
            organisationId: $organisation->id,
            status: $days <= 1 ? 'danger' : 'warning',
            icon: 'heroicon-o-credit-card',
            actionLabel: 'Voir les plans',
            actionUrl: $this->upgradeUrl($organisation),
        ));
    }

    private function isLatestFor(Subscription $subscription): bool
    {
        return ! Subscription::query()
            ->where('organisation_id', $subscription->organisation_id)
            ->where('id', '>', $subscription->id)
            ->exists();
    }

    private function upgradeUrl(Organisation $organisation): ?string
    {
        try {
            return route('filament.farm.pages.upgrade', ['tenant' => $organisation->slug]);
        } catch (\Throwable) {
            return null;
        }
    }
}
