<?php

namespace Database\Factories\Concerns;

use App\Models\Organisation;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Factories des tables de faits : `organisation_id` et `farm_id` suivent le parent
 * métier passé en attribut (employé, bande, vente, équipement…) ; à défaut, le parent
 * est créé dans l'organisation et la ferme passées en attributs, ou dans une
 * organisation neuve. Les clés `organisation_id` et `farm_id` doivent précéder la clé
 * du parent dans la définition : les closures sont évaluées dans l'ordre.
 */
trait InheritsOrganisationFromParent
{
    /**
     * Identifiant du parent passé en attribut (entier ou modèle), sinon null.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function parentKey(array $attributes, string $key): ?int
    {
        $value = $attributes[$key] ?? null;

        if ($value instanceof Model) {
            return (int) $value->getKey();
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  class-string<Model>  $parentModel
     */
    protected function organisationIdFrom(string $parentKey, string $parentModel): Closure
    {
        return function (array $attributes) use ($parentKey, $parentModel): int {
            $parentId = $this->parentKey($attributes, $parentKey);
            $organisationId = $parentId ? $parentModel::query()->whereKey($parentId)->value('organisation_id') : null;

            return $organisationId ? (int) $organisationId : Organisation::factory()->create()->id;
        };
    }

    /**
     * @param  class-string<Model>  $parentModel
     */
    protected function farmIdFrom(string $parentKey, string $parentModel): Closure
    {
        return function (array $attributes) use ($parentKey, $parentModel): ?int {
            $parentId = $this->parentKey($attributes, $parentKey);
            $farmId = $parentId ? $parentModel::query()->whereKey($parentId)->value('farm_id') : null;

            return $farmId ? (int) $farmId : null;
        };
    }

    /**
     * Attributs de rattachement à transmettre à la factory du parent (sans les nuls).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, int>
     */
    protected function scopeAttributes(array $attributes): array
    {
        return array_filter(
            ['organisation_id' => $attributes['organisation_id'] ?? null, 'farm_id' => $attributes['farm_id'] ?? null],
            fn ($value): bool => is_numeric($value),
        );
    }
}
