<?php

namespace App\Filament\Farm\Pages\Forecasts;

use App\Enums\PlanFeature;
use App\Enums\PredictionType;
use App\Filament\Farm\Concerns\RequiresOrganisationPermission;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\FlockPrediction;
use App\Services\CurrentFarm;
use App\Services\Forecast\ForecastService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Prévisions à 7 jours d'une bande : valeurs attendues (mortalité, ponte, poids, aliment) avec
 * leur confiance et les facteurs qui les expliquent, courbe réel / prévision / souche, précision
 * des prévisions passées. Calculées chaque nuit par gestavi:forecast.
 */
class ForecastsPage extends Page
{
    use RequiresOrganisationPermission, RequiresPlanFeatures {
        RequiresPlanFeatures::canAccess insteadof RequiresOrganisationPermission;
        RequiresOrganisationPermission::canAccess as canAccessWithPermission;
    }

    protected static string $requiredPermission = 'daily_records.view';

    /** Accessible si le plan inclut les prévisions ET si le membre peut consulter les relevés. */
    public static function canAccess(): bool
    {
        return static::tenantHasRequiredFeatures() && static::canAccessWithPermission();
    }

    /** Le trait de plan vise les ressources (canViewAny) : une page se fonde sur canAccess. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?string $navigationLabel = 'Prévisions';

    protected static ?string $title = 'Prévisions à 7 jours';

    protected static ?string $slug = 'previsions';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.farm.pages.forecasts';

    /** @var array<string, mixed> */
    public ?array $filters = [];

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::AiPredictions];
    }

    public function mount(): void
    {
        $flocks = $this->flockOptions();

        $this->filters = [
            'farm_id' => null,
            'flock_id' => array_key_first($flocks),
            'type' => null,
        ];
        $this->filters['type'] = $this->defaultType();
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('filters.farm_id')
                ->label('Ferme')
                ->options(function (): array {
                    $tenant = Filament::getTenant();

                    return Farm::query()
                        ->where('organisation_id', $tenant?->getKey())
                        ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->placeholder('Toutes les fermes')
                ->live()
                ->afterStateUpdated(function (): void {
                    $this->filters['flock_id'] = array_key_first($this->flockOptions());
                    $this->filters['type'] = $this->defaultType();
                }),
            Select::make('filters.flock_id')
                ->label('Bande')
                ->options(fn (): array => $this->flockOptions())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => $this->filters['type'] = $this->defaultType()),
            Select::make('filters.type')
                ->label('Grandeur')
                ->options(fn (): array => collect($this->availableTypes())->mapWithKeys(fn (PredictionType $t) => [$t->value => $t->getLabel()])->all())
                ->live(),
        ])->columns(['sm' => 1, 'lg' => 3]);
    }

    /**
     * @return array<int, string>
     */
    public function flockOptions(): array
    {
        $tenant = Filament::getTenant();

        return Flock::query()
            ->active()
            ->where('flocks.organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'flocks.farm_id'))
            ->when($this->filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where('flocks.farm_id', $farmId))
            ->with('farm:id,name')
            ->orderBy('flocks.code')
            ->get()
            ->mapWithKeys(fn (Flock $flock): array => [$flock->id => $flock->code.($flock->name ? ' – '.$flock->name : '').($flock->farm ? ' ('.$flock->farm->name.')' : '')])
            ->all();
    }

    public function selectedFlock(): ?Flock
    {
        $id = $this->filters['flock_id'] ?? null;

        return $id ? Flock::query()->with(['chickenType', 'farm'])->find($id) : null;
    }

    /**
     * @return list<PredictionType>
     */
    public function availableTypes(): array
    {
        $flock = $this->selectedFlock();

        return $flock ? app(ForecastService::class)->typesFor($flock) : PredictionType::cases();
    }

    public function defaultType(): ?string
    {
        $types = $this->availableTypes();
        $preferred = collect($types)->first(fn (PredictionType $t): bool => in_array($t, [PredictionType::LayingRate, PredictionType::Weight], true));

        return ($preferred ?? $types[0] ?? null)?->value;
    }

    public function selectedType(): PredictionType
    {
        return PredictionType::tryFrom((string) ($this->filters['type'] ?? '')) ?? PredictionType::Mortality;
    }

    /**
     * Prévision la plus lointaine (J+7) de chaque grandeur pour la bande.
     *
     * @return Collection<string, FlockPrediction>
     */
    public function horizonPredictions(): Collection
    {
        $flock = $this->selectedFlock();

        if (! $flock) {
            return new Collection;
        }

        return FlockPrediction::query()
            ->where('flock_id', $flock->id)
            ->whereDate('target_date', '>', Carbon::today())
            ->orderByDesc('target_date')
            ->get()
            ->unique(fn (FlockPrediction $p): string => $p->type->value)
            ->keyBy(fn (FlockPrediction $p): string => $p->type->value);
    }

    /**
     * Prévisions récentes de la grandeur choisie, les plus récentes d'abord.
     *
     * @return Collection<int, FlockPrediction>
     */
    public function recentPredictions(): Collection
    {
        $flock = $this->selectedFlock();

        if (! $flock) {
            return new Collection;
        }

        return FlockPrediction::query()
            ->where('flock_id', $flock->id)
            ->forType($this->selectedType())
            ->orderByDesc('target_date')
            ->limit(21)
            ->get();
    }

    public function dataPointsCount(): int
    {
        $flock = $this->selectedFlock();

        return $flock ? $flock->dailyRecords()->count() : 0;
    }

    public function organisationId(): ?int
    {
        return Filament::getTenant()?->getKey();
    }
}
