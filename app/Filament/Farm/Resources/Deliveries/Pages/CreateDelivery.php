<?php

namespace App\Filament\Farm\Resources\Deliveries\Pages;

use App\Filament\Farm\Resources\Deliveries\DeliveryResource;
use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDelivery extends CreateRecord
{
    protected static string $resource = DeliveryResource::class;

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // organisation_id et farm_id sont déduits de la facture par le trait BelongsToOrganisation.
    }

    /**
     * Pré-remplissage depuis une facture (?sale=ID) : destinataire et adresse du client.
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $saleId = request()->integer('sale');

        if ($saleId <= 0) {
            return;
        }

        $sale = Sale::query()->with('customer')
            ->where('organisation_id', Filament::getTenant()?->getKey())
            ->find($saleId);

        if ($sale) {
            $this->form->fill([
                'sale_id' => $sale->id,
                'delivery_date' => now()->toDateString(),
                'status' => 'planned',
                'recipient_name' => $sale->customer?->name,
                'recipient_phone' => $sale->customer?->phone,
                'delivery_address' => $sale->customer?->address,
            ]);
        }
    }
}
