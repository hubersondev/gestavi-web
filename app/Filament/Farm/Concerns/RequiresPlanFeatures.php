<?php

namespace App\Filament\Farm\Concerns;

use App\Enums\PlanFeature;
use App\Models\Organisation;
use Filament\Facades\Filament;

trait RequiresPlanFeatures
{
    /**
     * Fonctionnalités du plan requises pour accéder à cette resource/page.
     *
     * @return array<PlanFeature>
     */
    abstract public static function getRequiredPlanFeatures(): array;

    public static function shouldRegisterNavigation(): bool
    {
        return static::tenantHasRequiredFeatures() && static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::tenantHasRequiredFeatures() && static::canViewAny();
    }

    /**
     * Le plan du tenant couvre-t-il les fonctionnalités requises ? Indépendant des
     * permissions : un refus de permission se traduit par un 403, pas par une
     * invitation à changer de plan.
     */
    public static function tenantHasRequiredFeatures(): bool
    {
        /** @var Organisation|null $tenant */
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return false;
        }

        foreach (static::getRequiredPlanFeatures() as $feature) {
            if (! $tenant->hasFeature($feature)) {
                return false;
            }
        }

        return true;
    }
}
