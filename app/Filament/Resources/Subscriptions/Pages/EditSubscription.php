<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Correction manuelle (dates, statut, paiement). Le plan de l'organisation est resynchronisé
 * après enregistrement pour rester cohérent avec l'abonnement courant.
 */
class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['plan_id'])) {
            $data['plan_name'] = Plan::query()->whereKey($data['plan_id'])->value('name') ?? $data['plan_name'] ?? 'Plan';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Subscription $record */
        $record = $this->getRecord()->fresh();
        $organisation = $record->organisation;

        if ($record->isCurrent() && $organisation->plan_id !== $record->plan_id) {
            $organisation->forceFill(['plan_id' => $record->plan_id])->save();
        }
    }
}
