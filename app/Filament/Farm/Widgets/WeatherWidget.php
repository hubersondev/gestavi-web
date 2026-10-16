<?php

namespace App\Filament\Farm\Widgets;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\RequiresOrganisationPermission;
use App\Models\Farm;
use App\Models\Organisation;
use App\Services\CurrentFarm;
use App\Services\Weather\WeatherService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Météo des fermes du périmètre : conditions du jour, 5 jours, conditions à risque pour les
 * bandes et isolation des bâtiments. Réservé aux plans incluant l'intégration météo.
 */
class WeatherWidget extends Widget
{
    use RequiresOrganisationPermission;

    protected static string $requiredPermission = 'daily_records.view';

    protected string $view = 'filament.farm.widgets.weather';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Organisation
            && $tenant->hasFeature(PlanFeature::WeatherIntegration)
            && static::memberHasRequiredPermission();
    }

    /**
     * Jusqu'à trois fermes du périmètre (ferme sélectionnée en priorité), avec leurs données.
     *
     * @return list<array<string, mixed>>
     */
    public function getFarmsData(): array
    {
        $tenant = Filament::getTenant();
        $service = app(WeatherService::class);

        return Farm::query()
            ->where('organisation_id', $tenant?->getKey())
            ->where('is_active', true)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'id'))
            ->orderBy('name')
            ->limit(3)
            ->get()
            ->map(fn (Farm $farm): array => [
                'farm' => $farm,
                'located' => $farm->latitude !== null && $farm->longitude !== null,
                'current' => $service->current($farm),
                'upcoming' => $service->upcoming($farm, 5),
                'anomalies' => $service->anomaliesFor($farm),
                'insulation' => $service->buildingInsulation($farm),
                'fetched_at' => $service->lastFetchedAt($farm),
            ])
            ->all();
    }

    public function isConfigured(): bool
    {
        return WeatherService::enabled();
    }
}
