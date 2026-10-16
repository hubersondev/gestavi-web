<?php

namespace App\Filament\Farm\Pages;

use App\Models\Plan;
use App\Models\Subscription;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class UpgradePlan extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'upgrade';

    protected static ?string $title = 'Mettre à niveau votre plan';

    protected string $view = 'filament.farm.pages.upgrade-plan';

    public ?Plan $currentPlan = null;

    public ?Subscription $currentSubscription = null;

    /** @var Collection<int, Plan> */
    public $availablePlans;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->currentPlan = $tenant?->plan;
        $this->currentSubscription = $tenant?->currentSubscription();
        $this->availablePlans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get();
    }
}
