<?php

namespace App\Livewire;

use App\Filament\Farm\Resources\Farms\FarmResource;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Illuminate\View\View;
use Livewire\Component;

class FarmSelector extends Component
{
    public ?int $selectedFarmId = null;

    /** @var array<int, array{id: int, name: string}> */
    public array $farms = [];

    public bool $hasMultipleFarms = false;

    public function mount(): void
    {
        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return;
        }

        $membership = $user->membershipIn($tenant);

        if (! $membership) {
            return;
        }

        $service = app(CurrentFarm::class);
        $accessibleFarms = $service->getAccessibleFarms($membership);

        $this->hasMultipleFarms = $accessibleFarms->count() > 1;
        $this->farms = $accessibleFarms
            ->map(fn ($farm) => ['id' => $farm->id, 'name' => $farm->name])
            ->values()
            ->toArray();

        $this->selectedFarmId = $service->get($membership)?->id;
    }

    public function selectFarm(?string $farmId): void
    {
        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return;
        }

        $membership = $user->membershipIn($tenant);

        if (! $membership) {
            return;
        }

        app(CurrentFarm::class)->set($membership, $farmId ? (int) $farmId : null);

        // Dans une requête Livewire, request()->url() retourne l'endpoint Livewire.
        // Le header Referer contient l'URL réelle de la page affichée.
        $referer = request()->header('Referer', '');
        $farmsIndexUrl = FarmResource::getUrl('index');

        if ($farmId && str_starts_with($referer, $farmsIndexUrl)) {
            $this->redirect(Filament::getHomeUrl(), navigate: false);
        } else {
            $this->redirect($referer ?: Filament::getHomeUrl(), navigate: false);
        }
    }

    public function render(): View
    {
        return view('livewire.farm-selector');
    }
}
