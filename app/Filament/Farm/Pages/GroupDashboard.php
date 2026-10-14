<?php

namespace App\Filament\Farm\Pages;

use App\Models\Account;
use App\Models\Farm;
use App\Models\FarmDailyMetric;
use App\Services\CurrentFiscalYear;
use App\Services\FarmMetricsAggregator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Tableau de bord de direction : comparaison des fermes du groupe sur une période,
 * à partir des agrégats quotidiens pré-calculés (farm_daily_metrics).
 * Réservé aux membres à accès global (propriétaire, administrateur).
 */
class GroupDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Tableau de bord groupe';

    protected static ?string $title = 'Tableau de bord groupe';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'groupe';

    protected string $view = 'filament.farm.pages.group-dashboard';

    public ?array $filters = [];

    protected ?Collection $rowsCache = null;

    public static function canAccess(): bool
    {
        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

        return (bool) $membership?->canAccessAllFarms();
    }

    public function mount(): void
    {
        $this->filters = [
            ...app(CurrentFiscalYear::class)->reportPeriod(),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->displayFormat('d/m/Y')->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->displayFormat('d/m/Y')->live(),
        ])->columns(['sm' => 2, 'lg' => 4]);
    }

    public function updatedFilters(): void
    {
        $this->rowsCache = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Recalculer la période')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    [$from, $to] = $this->period();
                    $written = app(FarmMetricsAggregator::class)->aggregate($from, $to, Filament::getTenant()?->getKey());
                    $this->rowsCache = null;

                    Notification::make()->title("{$written} agrégat(s) ferme/jour recalculé(s).")->success()->send();
                }),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function period(): array
    {
        return [
            Carbon::parse($this->filters['date_from'] ?? now()->startOfMonth()),
            Carbon::parse($this->filters['date_to'] ?? now()),
        ];
    }

    /**
     * Une ligne par ferme : indicateurs de la période + trésorerie courante.
     */
    public function getRows(): Collection
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }

        [$from, $to] = $this->period();
        $organisationId = Filament::getTenant()?->getKey();

        $metrics = FarmDailyMetric::query()
            ->where('organisation_id', $organisationId)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('farm_id,
                MAX(active_flocks) AS active_flocks,
                SUM(mortality) AS mortality,
                SUM(culls) AS culls,
                SUM(eggs_collected) AS eggs,
                SUM(feed_kg) AS feed_kg,
                SUM(birds_present) AS bird_days,
                MAX(birds_present) AS birds_peak,
                SUM(sales_total) AS sales,
                SUM(payments_total) AS payments,
                SUM(expenses_total) AS expenses,
                MAX(computed_at) AS computed_at')
            ->groupBy('farm_id')
            ->get()
            ->keyBy('farm_id');

        $balances = Account::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->selectRaw('farm_id, SUM(current_balance) AS balance')
            ->groupBy('farm_id')
            ->pluck('balance', 'farm_id');

        $periodDays = max(1, (int) $from->diffInDays($to) + 1);

        $this->rowsCache = Farm::query()
            ->where('organisation_id', $organisationId)
            ->orderBy('name')
            ->get()
            ->map(function (Farm $farm) use ($metrics, $balances, $periodDays): array {
                $m = $metrics->get($farm->id);
                $birdDays = (int) ($m?->bird_days ?? 0);
                $mortality = (int) ($m?->mortality ?? 0);
                $eggs = (int) ($m?->eggs ?? 0);
                $sales = (float) ($m?->sales ?? 0);
                $expenses = (float) ($m?->expenses ?? 0);

                return [
                    'farm' => $farm->name,
                    'active_flocks' => (int) ($m?->active_flocks ?? 0),
                    'birds_peak' => (int) ($m?->birds_peak ?? 0),
                    'mortality' => $mortality,
                    // Mortalité rapportée à l'effectif moyen présent sur la période (sujets-jours / jours)
                    'mortality_rate' => $birdDays > 0 ? round($mortality / ($birdDays / $periodDays) * 100, 2) : null,
                    'eggs' => $eggs,
                    'laying_rate' => $birdDays > 0 ? round(($eggs / $birdDays) * 100, 1) : null,
                    'feed_kg' => (float) ($m?->feed_kg ?? 0),
                    'feed_per_bird_g' => $birdDays > 0 ? round(((float) ($m?->feed_kg ?? 0) * 1000) / $birdDays, 1) : null,
                    'sales' => $sales,
                    'payments' => (float) ($m?->payments ?? 0),
                    'expenses' => $expenses,
                    'result' => $sales - $expenses,
                    'balance' => (float) ($balances[$farm->id] ?? 0),
                    'computed_at' => $m?->computed_at ? Carbon::parse($m->computed_at)->format('d/m/Y H:i') : null,
                ];
            });

        return $this->rowsCache;
    }

    /**
     * @return array<string, float|int>
     */
    public function getTotals(): array
    {
        $rows = $this->getRows();

        return [
            'farms' => $rows->count(),
            'active_flocks' => (int) $rows->sum('active_flocks'),
            'birds_peak' => (int) $rows->sum('birds_peak'),
            'mortality' => (int) $rows->sum('mortality'),
            'eggs' => (int) $rows->sum('eggs'),
            'feed_kg' => (float) $rows->sum('feed_kg'),
            'sales' => (float) $rows->sum('sales'),
            'expenses' => (float) $rows->sum('expenses'),
            'result' => (float) $rows->sum('result'),
            'balance' => (float) $rows->sum('balance'),
        ];
    }
}
