<?php

namespace App\Filament\Farm\Pages;

use App\Models\Plan;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class UpgradePlan extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'upgrade';

    protected static ?string $title = 'Mettre à niveau votre plan';

    protected string $view = 'filament.farm.pages.upgrade-plan';

    public ?Plan $currentPlan = null;

    /** @var \Illuminate\Database\Eloquent\Collection<int, Plan> */
    public $availablePlans;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->currentPlan = $tenant?->plan;
        $this->availablePlans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get();
    }
}
