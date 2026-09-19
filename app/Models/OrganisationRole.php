<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganisationRole extends Model
{
    use HasFactory;

    /**
     * Poids minimum pour avoir accès à toutes les fermes (admin et au-dessus).
     */
    public const int FULL_ACCESS_WEIGHT = 3;

    protected $fillable = [
        'organisation_id',
        'name',
        'slug',
        'weight',
        'is_system',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    //  Relations
    // ──────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganisationPermission::class,
            'organisation_role_permission',
            'organisation_role_id',
            'organisation_permission_id',
        );
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganisationUser::class, 'organisation_role_id');
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }

    public function isAtLeast(self $role): bool
    {
        return $this->weight >= $role->weight;
    }

    public function isAbove(self $role): bool
    {
        return $this->weight > $role->weight;
    }

    public function isOwner(): bool
    {
        return $this->slug === 'owner';
    }

    public function isAdmin(): bool
    {
        return $this->weight >= self::FULL_ACCESS_WEIGHT;
    }

    /**
     * Libellé pour affichage (Filament).
     */
    public function getLabel(): string
    {
        return $this->name;
    }
}
