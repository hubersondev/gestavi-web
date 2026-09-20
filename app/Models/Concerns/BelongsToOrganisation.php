<?php

namespace App\Models\Concerns;

use App\Exceptions\OrganisationMismatchException;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Discriminant multi-tenant unique : toute table de faits porte `organisation_id`
 * (obligatoire) et, quand la notion existe, `farm_id`.
 *
 * À la création, les deux colonnes sont déduites du parent métier déclaré par
 * `$organisationParentRelation` (bâtiment, bande, compte, facture, stock, employé…)
 * ou de la ferme. À chaque écriture, la cohérence ferme ↔ organisation et
 * parent ↔ organisation est vérifiée : un identifiant d'une autre organisation
 * glissé dans une requête est refusé avant d'atteindre la base.
 */
trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        static::creating(function (Model $model): void {
            $model->fillOrganisationContext();
            $model->assertOrganisationContextConsistent();
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty(['organisation_id', 'farm_id', static::organisationParentForeignKey()])) {
                $model->fillOrganisationContext();
                $model->assertOrganisationContextConsistent();
            }
        });
    }

    /**
     * Relation vers le parent métier dont héritent organisation et ferme
     * (surcharger `$organisationParentRelation` dans le modèle ; `farm` par défaut).
     */
    protected static function organisationParentRelationName(): ?string
    {
        return property_exists(static::class, 'organisationParentRelation')
            ? static::$organisationParentRelation
            : (method_exists(static::class, 'farm') ? 'farm' : null);
    }

    protected static function organisationParentForeignKey(): ?string
    {
        $relation = static::organisationParentRelationName();

        if ($relation === null) {
            return null;
        }

        $instance = new static;

        return method_exists($instance, $relation) ? $instance->{$relation}()->getForeignKeyName() : null;
    }

    protected function hasFarmColumn(): bool
    {
        return in_array('farm_id', $this->getFillable(), true);
    }

    /**
     * Déduit organisation_id (et farm_id) manquants depuis la ferme ou le parent métier.
     */
    public function fillOrganisationContext(): void
    {
        $farmId = $this->hasFarmColumn() ? $this->getAttribute('farm_id') : null;

        if ($this->getAttribute('organisation_id') === null && $farmId) {
            $this->setAttribute('organisation_id', Farm::query()->whereKey($farmId)->value('organisation_id'));
        }

        $relation = static::organisationParentRelationName();

        if ($relation === null || $relation === 'farm' || ! method_exists($this, $relation)) {
            return;
        }

        if ($this->getAttribute('organisation_id') !== null && (! $this->hasFarmColumn() || $farmId !== null)) {
            return;
        }

        $parent = $this->{$relation}()->first();

        if (! $parent) {
            return;
        }

        if ($this->hasFarmColumn() && $farmId === null) {
            $parentFarmId = $parent instanceof Farm ? $parent->getKey() : $parent->getAttribute('farm_id');

            if ($parentFarmId === null && method_exists($parent, 'fillOrganisationContext')) {
                $parentFarmId = $parent->getAttribute('farm_id');
            }

            $this->setAttribute('farm_id', $parentFarmId);
        }

        if ($this->getAttribute('organisation_id') === null) {
            $this->setAttribute('organisation_id', $parent->getAttribute('organisation_id')
                ?? ($this->getAttribute('farm_id') ? Farm::query()->whereKey($this->getAttribute('farm_id'))->value('organisation_id') : null));
        }
    }

    /**
     * Refuse une ferme ou un parent d'une autre organisation.
     */
    public function assertOrganisationContextConsistent(): void
    {
        $organisationId = $this->getAttribute('organisation_id');

        if ($organisationId === null) {
            return;
        }

        $farmId = $this->hasFarmColumn() ? $this->getAttribute('farm_id') : null;

        if ($farmId !== null && ! Farm::query()->whereKey($farmId)->where('organisation_id', $organisationId)->exists()) {
            throw OrganisationMismatchException::farmOutsideOrganisation(static::class, (int) $farmId, (int) $organisationId);
        }

        $relation = static::organisationParentRelationName();

        if ($relation === null || $relation === 'farm' || ! method_exists($this, $relation)) {
            return;
        }

        $foreignKey = $this->{$relation}()->getForeignKeyName();

        if ($this->getAttribute($foreignKey) === null) {
            return;
        }

        $parent = $this->{$relation}()->first();
        $parentOrganisationId = $parent?->getAttribute('organisation_id');

        if ($parent && $parentOrganisationId !== null && (int) $parentOrganisationId !== (int) $organisationId) {
            throw OrganisationMismatchException::parentOutsideOrganisation(static::class, $relation, (int) $organisationId);
        }
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->where($query->qualifyColumn('organisation_id'), $organisationId);
    }
}
