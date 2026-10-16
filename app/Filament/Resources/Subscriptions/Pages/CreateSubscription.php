<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Enums\BillingCycle;
use App\Exceptions\SubscriptionException;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Organisation;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * La création passe par SubscriptionService : clôture de l'abonnement courant, calcul des dates
 * et du prix, synchronisation du plan de l'organisation, contrôle des limites.
 */
class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $organisation = Organisation::query()->findOrFail($data['organisation_id']);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        $trialDays = (int) ($this->form->getRawState()['trial_days'] ?? 0);

        try {
            return app(SubscriptionService::class)->start(
                organisation: $organisation,
                plan: $plan,
                cycle: BillingCycle::coerce($data['billing_cycle'] ?? null),
                trialDays: $trialDays,
                payment: [
                    'price' => isset($data['price']) ? (float) $data['price'] : null,
                    'payment_method' => $data['payment_method'] ?? null,
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
                by: Filament::auth()->user(),
                startsAt: isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null,
            );
        } catch (SubscriptionException $e) {
            Notification::make()->title('Souscription refusée')->body($e->getMessage())->danger()->send();

            throw ValidationException::withMessages(['data.plan_id' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
