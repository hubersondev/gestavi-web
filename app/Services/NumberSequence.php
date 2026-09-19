<?php

namespace App\Services;

use App\Models\Sequence;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Numérotation séquentielle et continue, sûre en concurrence.
 *
 * Chaque compteur (périmètre + clé + année) est une ligne de `sequences`
 * verrouillée en écriture le temps de la transaction appelante : deux
 * créations simultanées obtiennent des numéros distincts et consécutifs.
 *
 * À la première utilisation d'un compteur, `seedUsing` permet de repartir
 * du dernier numéro déjà présent en base (reprise de l'existant).
 */
class NumberSequence
{
    public const SCOPE_ORGANISATION = 'organisation';

    public const SCOPE_FARM = 'farm';

    public const SCOPE_EMPLOYEE = 'employee';

    public const SCOPE_CONTRACT = 'contract';

    /**
     * Retourne la prochaine valeur du compteur.
     *
     * @param  Closure(): int|null  $seedUsing  Valeur de départ (dernier numéro existant) si le compteur n'existe pas encore
     */
    public function next(
        string $key,
        int $scopeId,
        ?int $year = null,
        string $scope = self::SCOPE_ORGANISATION,
        ?Closure $seedUsing = null,
    ): int {
        return DB::transaction(function () use ($key, $scopeId, $year, $scope, $seedUsing): int {
            $sequence = $this->lockedRow($scope, $scopeId, $key, $year);

            if (! $sequence) {
                $sequence = $this->createRow($scope, $scopeId, $key, $year, $seedUsing ? max(0, (int) $seedUsing()) : 0);
            }

            $sequence->last_value += 1;
            $sequence->save();

            return $sequence->last_value;
        });
    }

    /**
     * Prochain numéro déjà formaté : {prefix}{year-}{valeur sur $pad chiffres}.
     *
     * @param  Closure(): int|null  $seedUsing
     */
    public function nextFormatted(
        string $prefix,
        string $key,
        int $scopeId,
        ?int $year = null,
        int $pad = 5,
        string $scope = self::SCOPE_ORGANISATION,
        ?Closure $seedUsing = null,
    ): string {
        $value = $this->next($key, $scopeId, $year, $scope, $seedUsing);

        return $year === null
            ? sprintf('%s%0'.$pad.'d', $prefix, $value)
            : sprintf('%s%d-%0'.$pad.'d', $prefix, $year, $value);
    }

    /**
     * Plus grand suffixe numérique déjà stocké dans une colonne, pour amorcer
     * un compteur à partir de l'existant (ex. FAC-2026-00042 → 42).
     *
     * @param  array<string, mixed>  $where
     */
    public function maxSuffix(string $table, string $column, string $prefix, array $where = []): int
    {
        $query = DB::table($table)->where($column, 'like', $prefix.'%');

        foreach ($where as $field => $value) {
            $value === null ? $query->whereNull($field) : $query->where($field, $value);
        }

        $max = $query->selectRaw("MAX(CAST(SUBSTRING({$column} FROM '[0-9]+$') AS INTEGER)) AS max_num")->value('max_num');

        return (int) $max;
    }

    private function lockedRow(string $scope, int $scopeId, string $key, ?int $year): ?Sequence
    {
        return Sequence::query()
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->when($year === null, fn ($q) => $q->whereNull('year'), fn ($q) => $q->where('year', $year))
            ->lockForUpdate()
            ->first();
    }

    private function createRow(string $scope, int $scopeId, string $key, ?int $year, int $seed): Sequence
    {
        try {
            return Sequence::query()->create([
                'scope' => $scope,
                'scope_id' => $scopeId,
                'key' => $key,
                'year' => $year,
                'last_value' => $seed,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Une transaction concurrente a créé la ligne entre-temps : on la verrouille.
            return $this->lockedRow($scope, $scopeId, $key, $year);
        }
    }
}
