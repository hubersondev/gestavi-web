<?php

namespace App\Filament\Farm\Resources\Suppliers\Pages;

use App\Filament\Farm\Resources\Suppliers\SupplierResource;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    /**
     * Le champ « Ferme » est masqué quand une ferme est déjà sélectionnée
     * (organisation mono-ferme) : on rattache alors le fournisseur à cette ferme.
     * Sans ferme, il s'agit d'un fournisseur réseau rattaché à l'organisation
     * (contrainte BDD : farm_id OU organisation_id obligatoire).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        if (empty($data['farm_id'])) {
            $membership = Filament::auth()->user()?->membershipIn($tenant);
            $currentFarm = $membership ? app(CurrentFarm::class)->get($membership) : null;

            $data['farm_id'] = $currentFarm?->id;
        }

        if (empty($data['farm_id'])) {
            $data['organisation_id'] = $tenant?->getKey();
        }

        return $data;
    }
}
