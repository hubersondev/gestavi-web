<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\PlanFeature;
use App\Enums\ProductionKind;
use App\Filament\Farm\Concerns\RequiresOrganisationPermission;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Pages\Reports\Concerns\ExportsReport;
use App\Services\CurrentFarm;
use App\Services\CurrentFiscalYear;
use App\Services\FarmComparisonService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Comparaison multi-fermes : 2 à 5 fermes côte à côte sur une période, indicateurs techniques
 * et économiques, graphiques en barres par indicateur et radar de scores normalisés. Filtre
 * optionnel sur le type de production (chair, pondeuse…).
 */
class FarmComparisonPage extends Page
{
    use ExportsReport;
    use RequiresOrganisationPermission, RequiresPlanFeatures {
        RequiresPlanFeatures::canAccess insteadof RequiresOrganisationPermission;
        RequiresOrganisationPermission::canAccess as canAccessWithPermission;
    }

    protected static string $requiredPermission = 'daily_records.view';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Comparaison des fermes';

    protected static ?string $title = 'Comparaison multi-fermes';

    protected static ?string $slug = 'comparaison-fermes';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.farm.pages.reports.farm-comparison';

    public ?array $filters = [];

    /** @var Collection<int, array<string, mixed>>|null */
    protected ?Collection $rowsCache = null;

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::MultiFarmComparison];
    }

    /** Accessible si le plan inclut la comparaison ET si le membre peut consulter les relevés. */
    public static function canAccess(): bool
    {
        return static::tenantHasRequiredFeatures() && static::canAccessWithPermission();
    }

    /** Le trait de plan vise les ressources (canViewAny) : une page se fonde sur canAccess. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->filters = [
            ...app(CurrentFiscalYear::class)->reportPeriod(),
            'farm_ids' => array_slice(array_keys($this->farmOptions()), 0, FarmComparisonService::MAX_FARMS),
            'production_kind' => null,
        ];
    }

    public function reportKey(): string
    {
        return 'comparaison-fermes';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->live(),
            Select::make('filters.farm_ids')
                ->label('Fermes à comparer')
                ->multiple()
                ->options(fn (): array => $this->farmOptions())
                ->minItems(FarmComparisonService::MIN_FARMS)
                ->maxItems(FarmComparisonService::MAX_FARMS)
                ->helperText('De 2 à 5 fermes.')
                ->live(),
            Select::make('filters.production_kind')
                ->label('Type de production')
                ->options(ProductionKind::class)
                ->placeholder('Toutes les productions')
                ->live(),
        ])->columns(['sm' => 2, 'lg' => 4]);
    }

    public function updatedFilters(): void
    {
        $this->rowsCache = null;
    }

    /**
     * @return array<int, string>
     */
    public function farmOptions(): array
    {
        $membership = app(CurrentFarm::class)->currentMembership();

        if (! $membership) {
            return [];
        }

        return app(FarmComparisonService::class)->comparableFarms($membership)->pluck('name', 'id')->all();
    }

    /**
     * Fermes demandées, limitées à celles que le membre peut comparer.
     *
     * @return list<int>
     */
    public function selectedFarmIds(): array
    {
        $allowed = array_keys($this->farmOptions());
        $requested = array_map(intval(...), (array) ($this->filters['farm_ids'] ?? []));

        return array_values(array_intersect($requested, $allowed));
    }

    public function productionKind(): ?ProductionKind
    {
        $value = $this->filters['production_kind'] ?? null;

        return $value ? ProductionKind::tryFrom((string) $value) : null;
    }

    public function hasEnoughFarms(): bool
    {
        return count($this->selectedFarmIds()) >= FarmComparisonService::MIN_FARMS;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getRows(): Collection
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }

        if (! $this->hasEnoughFarms()) {
            return $this->rowsCache = collect();
        }

        $filters = $this->normalizedFilters();

        return $this->rowsCache = app(FarmComparisonService::class)->compare(
            (int) Filament::getTenant()?->getKey(),
            $this->selectedFarmIds(),
            $filters['date_from'],
            $filters['date_to'],
            $this->productionKind(),
        );
    }

    /**
     * @return array{axes: array<string, string>, scores: array<int, array<string, float>>}
     */
    public function getScores(): array
    {
        return app(FarmComparisonService::class)->scores($this->getRows(), $this->productionKind());
    }

    /**
     * Ferme en tête sur chaque indicateur (pour surligner la meilleure valeur du tableau).
     *
     * @return array<string, int|null>
     */
    public function getLeaders(): array
    {
        $rows = $this->getRows();
        $leaders = [];

        foreach (['mortality_rate' => false, 'laying_rate' => true, 'avg_weight_g' => true, 'feed_per_bird_day_g' => false, 'sales' => true, 'result' => true] as $metric => $higherIsBetter) {
            $candidates = $rows->filter(fn (array $r): bool => $r[$metric] !== null && ($metric === 'result' || $r[$metric] > 0));
            $leaders[$metric] = $candidates->isEmpty() ? null : (int) ($higherIsBetter ? $candidates->sortByDesc($metric) : $candidates->sortBy($metric))->first()['farm_id'];
        }

        return $leaders;
    }

    /**
     * Indicateurs à tracer en barres selon le type de production.
     *
     * @return list<string>
     */
    public function chartMetrics(): array
    {
        return match ($this->productionKind()) {
            ProductionKind::Broiler => ['mortality_rate', 'avg_weight_g', 'feed_per_bird_day_g', 'sales'],
            ProductionKind::Layer => ['mortality_rate', 'laying_rate', 'feed_per_bird_day_g', 'sales'],
            default => ['mortality_rate', 'laying_rate', 'avg_weight_g', 'sales'],
        };
    }

    /**
     * Paramètres passés aux widgets graphiques.
     *
     * @return array<string, mixed>
     */
    public function chartParams(): array
    {
        $filters = $this->normalizedFilters();

        return [
            'organisationId' => (int) Filament::getTenant()?->getKey(),
            'farmIds' => $this->selectedFarmIds(),
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'productionKind' => $this->productionKind()?->value,
        ];
    }

    public function buildXlsx(): string
    {
        $rows = $this->getRows();
        $scores = $this->getScores();
        $filters = $this->normalizedFilters();

        return $this->xlsxToString(function (callable $addRow) use ($rows, $scores, $filters): void {
            $addRow(['Comparaison multi-fermes du '.$filters['date_from'].' au '.$filters['date_to'].($this->productionKind() ? ' — '.$this->productionKind()->getLabel() : '')]);
            $addRow([]);
            $addRow([
                'Ferme', 'Bandes actives', 'Effectif', 'Bandes relevées', 'Sujets-jours',
                'Mortalité', 'Réformes', 'Vols', 'Taux de mortalité (%)',
                'Œufs', 'Œufs/jour', 'Taux de ponte (%)', 'Poids moyen (g)',
                'Aliment (kg)', 'Aliment g/sujet/jour',
                'Ventes (XOF)', 'Dépenses (XOF)', 'Ventes − dépenses (XOF)', 'Ventes par sujet (XOF)',
            ]);

            foreach ($rows as $r) {
                $addRow([
                    $r['farm_name'], $r['active_flocks'], $r['birds'], $r['flocks_recorded'], $r['bird_days'],
                    $r['mortality'], $r['culls'], $r['thefts'], $r['mortality_rate'],
                    $r['eggs'], $r['eggs_per_day'], $r['laying_rate'], $r['avg_weight_g'],
                    $r['feed_kg'], $r['feed_per_bird_day_g'],
                    $r['sales'], $r['expenses'], $r['result'], $r['sales_per_bird'],
                ]);
            }

            $addRow([]);
            $addRow(['Scores (100 = meilleure ferme de l\'axe)']);
            $addRow(['Ferme', ...array_values($scores['axes'])]);

            foreach ($rows as $r) {
                $addRow([$r['farm_name'], ...array_values($scores['scores'][$r['farm_id']] ?? [])]);
            }
        });
    }
}
