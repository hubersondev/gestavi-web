<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\UserType;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'type',
        'is_active',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'type' => UserType::class,
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    // ──────────────────────────────────────────────
    //  Authentification à deux facteurs (application TOTP)
    // ──────────────────────────────────────────────

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return array<string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /**
     * Le 2FA est exigé des utilisateurs plateforme et des membres propriétaires ou
     * administrateurs d'au moins une organisation (accès à la trésorerie et à la paie).
     */
    public function mustUseTwoFactor(): bool
    {
        if ($this->isPlatformUser()) {
            return true;
        }

        return OrganisationUser::query()
            ->where('user_id', $this->getKey())
            ->whereHas('organisationRole', fn (Builder $q) => $q->where('weight', '>=', OrganisationRole::FULL_ACCESS_WEIGHT))
            ->exists();
    }

    /**
     * Initiales de l'utilisateur pour l'avatar.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn (string $word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    // ──────────────────────────────────────────────
    //  Relations
    // ──────────────────────────────────────────────

    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_role_user')
            ->withTimestamps();
    }

    /**
     * Retourne le rôle plateforme assigné (un seul par utilisateur).
     */
    public function getPlatformRoleAttribute(): ?PlatformRole
    {
        return $this->platformRoles->first();
    }

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisation_users')
            ->using(OrganisationUser::class)
            ->withPivot(['id', 'organisation_role_id', 'permissions', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function activeOrganisations(): BelongsToMany
    {
        return $this->organisations()
            ->wherePivot('status', MemberStatus::Active);
    }

    public function ownedOrganisations(): BelongsToMany
    {
        return $this->activeOrganisations()
            ->whereIn('organisation_users.organisation_role_id', function ($query) {
                $query->select('id')
                    ->from('organisation_roles')
                    ->whereColumn('organisation_roles.organisation_id', 'organisations.id')
                    ->where('slug', 'owner');
            });
    }

    // ──────────────────────────────────────────────
    //  Multi-tenancy Filament
    // ──────────────────────────────────────────────

    public function getTenants(Panel $panel): Collection
    {
        return $this->activeOrganisations()->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->organisations()
            ->wherePivot('status', MemberStatus::Active)
            ->whereKey($tenant)
            ->exists();
    }

    // ──────────────────────────────────────────────
    //  Accès aux panels
    // ──────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->isPlatformUser(),
            'farm' => ! $this->isPlatformUser(),
            default => false,
        };
    }

    // ──────────────────────────────────────────────
    //  Helpers plateforme
    // ──────────────────────────────────────────────

    public function isPlatformUser(): bool
    {
        return $this->type === UserType::System;
    }

    public function isSuperAdmin(): bool
    {
        return $this->platformRole?->isSuperAdmin() ?? false;
    }

    /**
     * Vérifie si l'utilisateur possède une permission plateforme donnée.
     */
    public function hasPermission(string $slug): bool
    {
        if (! $this->isPlatformUser()) {
            return false;
        }

        // Super admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->platformRole?->hasPermission($slug) ?? false;
    }

    // ──────────────────────────────────────────────
    //  Helpers organisation
    // ──────────────────────────────────────────────

    public function membershipIn(Organisation $organisation): ?OrganisationUser
    {
        return $this->organisations()
            ->where('organisation_users.organisation_id', $organisation->id)
            ->first()
            ?->pivot;
    }

    public function roleInOrganisation(Organisation $organisation): ?OrganisationRole
    {
        return $this->membershipIn($organisation)?->organisationRole;
    }

    public function hasOrganisationPermission(Organisation $organisation, string $permissionSlug): bool
    {
        $membership = $this->membershipIn($organisation);

        if (! $membership) {
            return false;
        }

        return $membership->hasPermission($permissionSlug);
    }

    public function isOrganisationAdmin(Organisation $organisation): bool
    {
        return $this->membershipIn($organisation)?->isAdmin() ?? false;
    }

    // ──────────────────────────────────────────────
    //  Scopes
    // ──────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePlatformUsers(Builder $query): Builder
    {
        return $query->where('type', UserType::System);
    }

    public function scopeFarmUsers(Builder $query): Builder
    {
        return $query->where('type', UserType::Farmer);
    }

    public function scopeInOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->whereHas(
            'organisations',
            fn (Builder $q) => $q->where('organisation_users.organisation_id', $organisationId)
                ->where('organisation_users.status', MemberStatus::Active)
        );
    }

    // ──────────────────────────────────────────────
    //  Lifecycle
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->isForceDeleting()) {
                return;
            }

            $organisationIds = $user->organisations()->pluck('organisations.id')->toArray();

            if (! empty($organisationIds)) {
                $user->organisations()->updateExistingPivot(
                    $organisationIds,
                    ['status' => MemberStatus::Suspended],
                );
            }
        });

        static::restored(function (User $user) {
            $organisationIds = $user->organisations()->pluck('organisations.id')->toArray();

            if (! empty($organisationIds)) {
                $user->organisations()->updateExistingPivot(
                    $organisationIds,
                    ['status' => MemberStatus::Active],
                );
            }
        });
    }
}
