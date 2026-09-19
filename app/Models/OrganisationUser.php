<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganisationUser extends Pivot
{
    protected $table = 'organisation_users';

    public $incrementing = true;

    protected $fillable = [
        'organisation_id',
        'user_id',
        'organisation_role_id',
        'permissions',
        'joined_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'permissions' => 'array',
            'joined_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisationRole(): BelongsTo
    {
        return $this->belongsTo(OrganisationRole::class);
    }

    // ──────────────────────────────────────────────
    //  Relations
    // ──────────────────────────────────────────────

    /**
     * Fermes explicitement assignées à ce membre.
     */
    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_organisation_user', 'organisation_user_id', 'farm_id')
            ->withTimestamps();
    }

    // ──────────────────────────────────────────────
    //  Helpers rôle (via FK)
    // ──────────────────────────────────────────────

    public function isAtLeast(OrganisationRole $role): bool
    {
        return $this->organisationRole && $this->organisationRole->isAtLeast($role);
    }

    public function isAbove(OrganisationRole $role): bool
    {
        return $this->organisationRole && $this->organisationRole->isAbove($role);
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }

    public function isInvited(): bool
    {
        return $this->status === MemberStatus::Invited;
    }

    public function isSuspended(): bool
    {
        return $this->status === MemberStatus::Suspended;
    }

    public function isOwner(): bool
    {
        return $this->organisationRole?->isOwner() ?? false;
    }

    public function isAdmin(): bool
    {
        return $this->organisationRole?->isAdmin() ?? false;
    }

    /**
     * Vérifie si le membre a au moins le poids du rôle donné.
     */
    public function hasMinimumWeight(int $weight): bool
    {
        return ($this->organisationRole?->weight ?? 0) >= $weight;
    }

    /**
     * Vérifie si le membre possède une permission d'organisation donnée.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        return $this->organisationRole?->hasPermission($slug) ?? false;
    }

    /**
     * Vérifie si le membre a un rôle suffisant pour voir toutes les fermes.
     */
    public function canAccessAllFarms(): bool
    {
        return ($this->organisationRole?->weight ?? 0) >= OrganisationRole::FULL_ACCESS_WEIGHT;
    }

    /**
     * Vérifie si ce membre est le dernier propriétaire de l'organisation.
     */
    public function isLastOwner(): bool
    {
        if (! $this->isOwner()) {
            return false;
        }

        return static::query()
            ->where('organisation_id', $this->organisation_id)
            ->whereHas('organisationRole', fn ($q) => $q->where('slug', 'owner'))
            ->count() <= 1;
    }

    /**
     * Suspendre le membre.
     */
    public function suspend(): void
    {
        $this->update(['status' => MemberStatus::Suspended]);
    }

    /**
     * Réactiver le membre (depuis Suspendu ou Invité).
     */
    public function reactivate(): void
    {
        $this->update([
            'status' => MemberStatus::Active,
            'joined_at' => $this->joined_at ?? now(),
        ]);
    }
}
