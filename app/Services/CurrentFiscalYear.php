<?php

namespace App\Services;

use App\Models\FiscalYear;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

/**
 * Exercice comptable actif choisi dans la barre du haut : un filtre d'affichage des journaux datés
 * et une valeur par défaut pour les dates et les périodes de rapport. Jamais une règle de gestion :
 * les services (clôture, à-nouveaux, reconstruction, API, synchronisation terrain) l'ignorent.
 */
class CurrentFiscalYear
{
    private const string ALL = 'all';

    /**
     * Exercices de l'organisation courante, du plus récent au plus ancien.
     *
     * @return Collection<int, FiscalYear>
     */
    public function options(): Collection
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return new Collection;
        }

        // Mémorisé le temps de la requête (service « scoped ») : chaque ligne de liste et chaque champ de date interrogent l'exercice
        return $this->optionsCache[$tenant->getKey()] ??= FiscalYear::query()
            ->where('organisation_id', $tenant->getKey())
            ->orderByDesc('starts_on')
            ->get();
    }

    /** @var array<int|string, Collection<int, FiscalYear>> */
    private array $optionsCache = [];

    public function forgetCache(): void
    {
        $this->optionsCache = [];
    }

    /**
     * Exercice actif, ou null pour « tous les exercices ». Sans choix explicite : celui qui couvre
     * aujourd'hui, s'il existe.
     */
    public function get(): ?FiscalYear
    {
        $options = $this->options();

        if ($options->isEmpty()) {
            return null;
        }

        $selected = Session::get($this->sessionKey());

        if ($selected === self::ALL) {
            return null;
        }

        if ($selected !== null) {
            $fiscalYear = $options->firstWhere('id', (int) $selected);

            if ($fiscalYear) {
                return $fiscalYear;
            }
        }

        return $options->first(fn (FiscalYear $fiscalYear): bool => $fiscalYear->covers(today()));
    }

    /**
     * Choix explicite : un identifiant d'exercice, ou null pour « tous les exercices ».
     */
    public function set(?int $fiscalYearId): void
    {
        $key = $this->sessionKey();

        if ($key === null) {
            return;
        }

        Session::put($key, $fiscalYearId ?? self::ALL);
        $this->forgetCache();
    }

    /**
     * @return array{0: string, 1: string}|null bornes de l'exercice actif, au format AAAA-MM-JJ
     */
    public function period(): ?array
    {
        $fiscalYear = $this->get();

        return $fiscalYear ? [$fiscalYear->starts_on->toDateString(), $fiscalYear->ends_on->toDateString()] : null;
    }

    /**
     * Restreint une liste à la période de l'exercice actif.
     */
    public function scope(Builder $query, string $column): Builder
    {
        $period = $this->period();

        if ($period === null) {
            return $query;
        }

        $column = str_contains($column, '.') ? $column : $query->qualifyColumn($column);

        return $query->whereBetween($column, $period);
    }

    /**
     * Variante pour la paie, dont la période est portée par (period_year, period_month).
     */
    public function scopePayrollPeriod(Builder $query): Builder
    {
        $period = $this->period();

        if ($period === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->whereRaw("make_date({$table}.period_year, {$table}.period_month, 1) BETWEEN ? AND ?", [
            Carbon::parse($period[0])->startOfMonth()->toDateString(),
            $period[1],
        ]);
    }

    /**
     * Filtre et rappel visible sur une table de ressource.
     */
    public function applyToTable(Table $table, string $column): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $column === 'payroll_period'
                ? $this->scopePayrollPeriod($query)
                : $this->scope($query, $column))
            ->description(fn (): ?string => $this->notice());
    }

    /**
     * Date proposée pour une nouvelle pièce : aujourd'hui si l'exercice actif le couvre, sinon le
     * jour de l'exercice le plus proche d'aujourd'hui (dernier jour d'un exercice passé, premier
     * jour d'un exercice futur).
     */
    public function defaultDate(): CarbonInterface
    {
        $fiscalYear = $this->get();
        $today = today();

        if (! $fiscalYear || $fiscalYear->covers($today)) {
            return $today;
        }

        return $fiscalYear->ends_on->lessThan($today) ? $fiscalYear->ends_on->copy() : $fiscalYear->starts_on->copy();
    }

    /**
     * Période par défaut des rapports : l'exercice actif borné à aujourd'hui, sinon le mois en cours.
     *
     * @return array{date_from: string, date_to: string}
     */
    public function reportPeriod(?CarbonInterface $fallbackFrom = null): array
    {
        $fiscalYear = $this->get();

        if (! $fiscalYear) {
            return ['date_from' => ($fallbackFrom ?? now()->startOfMonth())->toDateString(), 'date_to' => now()->toDateString()];
        }

        $to = $fiscalYear->ends_on->lessThan(today()) ? $fiscalYear->ends_on : today();

        return ['date_from' => $fiscalYear->starts_on->toDateString(), 'date_to' => $to->toDateString()];
    }

    /**
     * Avertissement non bloquant quand une date sort de l'exercice actif.
     */
    public function dateWarning(mixed $date): ?string
    {
        $fiscalYear = $this->get();

        if (! $fiscalYear || blank($date)) {
            return null;
        }

        try {
            $day = Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }

        if ($fiscalYear->covers($day)) {
            return null;
        }

        return sprintf(
            'Cette date est hors de l\'exercice actif « %s » (%s – %s) : la pièce sera enregistrée mais n\'apparaîtra pas dans les listes filtrées sur cet exercice.',
            $fiscalYear->label,
            $fiscalYear->starts_on->format('d/m/Y'),
            $fiscalYear->ends_on->format('d/m/Y'),
        );
    }

    public function notice(): ?string
    {
        $fiscalYear = $this->get();

        if (! $fiscalYear) {
            return null;
        }

        return sprintf(
            'Exercice « %s » (%s – %s) : seules les pièces datées dans cette période sont affichées. Changez d\'exercice dans la barre du haut.',
            $fiscalYear->label,
            $fiscalYear->starts_on->format('d/m/Y'),
            $fiscalYear->ends_on->format('d/m/Y'),
        );
    }

    private function sessionKey(): ?string
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();

        if (! $tenant || ! $user) {
            return null;
        }

        return "current_fiscal_year_{$tenant->getKey()}_{$user->getKey()}";
    }
}
