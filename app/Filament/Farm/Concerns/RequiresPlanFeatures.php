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

    protected static function tenantHasRequiredFeatures(): bool
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
