<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organisation_id',
        'code',
        'name',
        'capacity',
        'address',
        'city_id',
        'latitude',
        'longitude',
        'phone',
        'email',
        'chicken_type_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Une ferme supprimée (même logiquement) ne doit plus apparaître dans les
     * affectations des membres : les lignes du pivot sont retirées.
     */
    protected static function booted(): void
    {
        static::deleting(function (Farm $farm): void {
            $farm->assignedMembers()->detach();
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function chickenType(): BelongsTo
    {
        return $this->belongsTo(ChickenType::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Membres explicitement assignés à cette ferme.
     */
    public function assignedMembers(): BelongsToMany
    {
        return $this->belongsToMany(OrganisationUser::class, 'farm_organisation_user', 'farm_id', 'organisation_user_id')
            ->withTimestamps();
    }

    /**
     * Filtre les fermes accessibles par un membre de l'organisation.
     * Weight >= 3 (admin/owner) : toutes les fermes de l'organisation.
     * Weight < 3 (manager/employee) : uniquement les fermes assignées.
     */
    public function scopeAccessibleBy(Builder $query, OrganisationUser $membership): Builder
    {
        if ($membership->canAccessAllFarms()) {
            return $query;
        }

        return $query->whereHas('assignedMembers', function (Builder $q) use ($membership) {
            $q->where('farm_organisation_user.organisation_user_id', $membership->id);
        });
    }
}
