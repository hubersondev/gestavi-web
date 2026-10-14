<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use App\Services\CurrentFiscalYear;
use Filament\Facades\Filament;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Sélecteur d'exercice comptable actif dans la barre du haut du panel Farm.
 * Invisible tant que l'organisation n'a défini aucun exercice.
 */
class FiscalYearSelector extends Component
{
    public ?int $selectedFiscalYearId = null;

    /** @var array<int, array{id: int, label: string}> */
    public array $fiscalYears = [];

    public function mount(): void
    {
        $service = app(CurrentFiscalYear::class);

        $this->fiscalYears = $service->options()
            ->map(fn (FiscalYear $fiscalYear): array => ['id' => $fiscalYear->id, 'label' => $fiscalYear->label])
            ->values()
            ->all();

        $this->selectedFiscalYearId = $service->get()?->id;
    }

    public function selectFiscalYear(?string $fiscalYearId): void
    {
        app(CurrentFiscalYear::class)->set($fiscalYearId ? (int) $fiscalYearId : null);

        // Dans une requête Livewire, request()->url() pointe sur l'endpoint Livewire : le Referer porte la vraie page
        $referer = request()->header('Referer', '');

        $this->redirect($referer ?: Filament::getHomeUrl(), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.fiscal-year-selector');
    }
}
