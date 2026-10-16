<?php

namespace App\Models;

use App\Enums\ProductionKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Courbe de référence d'une souche (Cobb 500, ISA Brown…) à un âge donné : poids, ponte,
 * aliment, mortalité cumulée attendus. Référentiel partagé, administré depuis le panel Admin.
 * Entre deux âges connus, la valeur est interpolée linéairement.
 */
class BreedStandard extends Model
{
    public const TYPE_LAYER = 'layer';

    public const TYPE_BROILER = 'broiler';

    protected $fillable = [
        'breed', 'bird_type', 'age_days',
        'expected_weight_g', 'expected_daily_gain_g', 'expected_feed_intake_g', 'expected_feed_per_day_g',
        'expected_fcr', 'expected_laying_rate', 'expected_egg_weight_g', 'expected_mortality_cumul',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'age_days' => 'integer',
            'expected_weight_g' => 'decimal:2',
            'expected_daily_gain_g' => 'decimal:2',
            'expected_feed_intake_g' => 'decimal:2',
            'expected_feed_per_day_g' => 'decimal:2',
            'expected_fcr' => 'decimal:2',
            'expected_laying_rate' => 'decimal:2',
            'expected_egg_weight_g' => 'decimal:2',
            'expected_mortality_cumul' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Type de standard à consulter pour une grandeur donnée selon le type de production : une
     * bande mixte lit la ponte sur la courbe pondeuse et le poids sur la courbe chair.
     */
    public static function birdTypeFor(ProductionKind $kind, string $metricColumn): string
    {
        return match ($kind) {
            ProductionKind::Broiler => self::TYPE_BROILER,
            ProductionKind::Layer, ProductionKind::Breeder => self::TYPE_LAYER,
            ProductionKind::Mixed => $metricColumn === 'expected_weight_g' ? self::TYPE_BROILER : self::TYPE_LAYER,
        };
    }

    /**
     * Clé de rapprochement d'un libellé de souche saisi librement : « isa brown », « ISA-Brown »
     * et « Isa Brown » désignent la même courbe.
     */
    public static function normalizeBreed(?string $breed): string
    {
        return Str::of((string) $breed)->ascii()->lower()->replaceMatches('/[^a-z0-9]/', '')->toString();
    }

    /**
     * Souches connues, pour proposer la saisie sur la fiche de bande.
     *
     * @return list<string>
     */
    public static function breeds(): array
    {
        return static::query()->active()->distinct()->orderBy('breed')->pluck('breed')->all();
    }

    /**
     * Points actifs d'une souche et d'un type, dans l'ordre des âges ; vide si la souche est inconnue.
     *
     * @return Collection<int, static>
     */
    public static function curveFor(?string $breed, string $birdType): Collection
    {
        $key = self::normalizeBreed($breed);

        if ($key === '') {
            return new Collection;
        }

        return static::query()
            ->active()
            ->where('bird_type', $birdType)
            ->orderBy('age_days')
            ->get()
            ->filter(fn (self $row): bool => self::normalizeBreed($row->breed) === $key)
            ->values();
    }

    /**
     * Valeur attendue d'une grandeur à un âge, interpolée entre les deux points connus les plus
     * proches ; null si la souche est inconnue ou la grandeur jamais renseignée.
     */
    public static function expectedValue(?string $breed, ProductionKind $kind, string $metricColumn, int $ageDays): ?float
    {
        $points = self::curveFor($breed, self::birdTypeFor($kind, $metricColumn))
            ->filter(fn (self $row): bool => $row->{$metricColumn} !== null)
            ->values();

        if ($points->isEmpty()) {
            return null;
        }

        /** @var self $first */
        $first = $points->first();
        /** @var self $last */
        $last = $points->last();

        if ($ageDays <= $first->age_days) {
            return (float) $first->{$metricColumn};
        }

        if ($ageDays >= $last->age_days) {
            return (float) $last->{$metricColumn};
        }

        for ($i = 1; $i < $points->count(); $i++) {
            /** @var self $low */
            $low = $points[$i - 1];
            /** @var self $high */
            $high = $points[$i];

            if ($ageDays >= $low->age_days && $ageDays <= $high->age_days) {
                $span = $high->age_days - $low->age_days;
                $ratio = $span > 0 ? ($ageDays - $low->age_days) / $span : 0;

                return round((float) $low->{$metricColumn} + $ratio * ((float) $high->{$metricColumn} - (float) $low->{$metricColumn}), 2);
            }
        }

        return (float) $last->{$metricColumn};
    }
}
