<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\PlanFeature;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organisation extends Model implements HasCurrentTenantLabel
{
    use HasFactory, SoftDeletes;

    /**
     * Phases de production créées par défaut pour chaque nouvelle organisation.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    public const DEFAULT_PRODUCTION_PHASES = [
        'Chair' => [
            [
                'name' => 'Démarrage Chair',
                'code' => 'CHAIR_START',
                'start_day' => 1,
                'end_day' => 10,
                'description' => 'Phase de démarrage des poulets de chair',
                'min_temperature' => 32,
                'max_temperature' => 34,
                'humidity_min' => 60,
                'humidity_max' => 70,
                'light_hours' => 23,
                'density_per_m2' => 40,
                'feed_type' => 'Aliment démarrage poulet chair',
                'order' => 1,
            ],
            [
                'name' => 'Croissance Chair',
                'code' => 'CHAIR_GROWTH',
                'start_day' => 11,
                'end_day' => 28,
                'description' => 'Phase de croissance rapide',
                'min_temperature' => 24,
                'max_temperature' => 28,
                'humidity_min' => 55,
                'humidity_max' => 65,
                'light_hours' => 20,
                'density_per_m2' => 15,
                'feed_type' => 'Aliment croissance poulet chair',
                'order' => 2,
            ],
            [
                'name' => 'Finition Chair',
                'code' => 'CHAIR_FINISH',
                'start_day' => 29,
                'end_day' => 45,
                'description' => 'Phase de finition avant abattage',
                'min_temperature' => 20,
                'max_temperature' => 24,
                'humidity_min' => 50,
                'humidity_max' => 60,
                'light_hours' => 18,
                'density_per_m2' => 10,
                'feed_type' => 'Aliment finition poulet chair',
                'order' => 3,
            ],
        ],
        'Pondeuse' => [
            [
                'name' => 'Démarrage Pondeuse',
                'code' => 'PONTE_START',
                'start_day' => 1,
                'end_day' => 42,
                'description' => 'Phase de démarrage des poulettes',
                'min_temperature' => 32,
                'max_temperature' => 35,
                'humidity_min' => 60,
                'humidity_max' => 70,
                'light_hours' => 22,
                'density_per_m2' => 50,
                'feed_type' => 'Aliment démarrage poulette',
                'order' => 1,
            ],
            [
                'name' => 'Croissance Pondeuse',
                'code' => 'PONTE_GROWTH',
                'start_day' => 43,
                'end_day' => 112,
                'description' => 'Phase de croissance des poulettes',
                'min_temperature' => 22,
                'max_temperature' => 26,
                'humidity_min' => 55,
                'humidity_max' => 65,
                'light_hours' => 10,
                'density_per_m2' => 12,
                'feed_type' => 'Aliment croissance poulette',
                'order' => 2,
            ],
            [
                'name' => 'Pré-ponte',
                'code' => 'PONTE_PREPONTE',
                'start_day' => 113,
                'end_day' => 140,
                'description' => 'Phase de préparation à la ponte',
                'min_temperature' => 20,
                'max_temperature' => 24,
                'humidity_min' => 50,
                'humidity_max' => 60,
                'light_hours' => 12,
                'density_per_m2' => 10,
                'feed_type' => 'Aliment pré-ponte',
                'order' => 3,
            ],
            [
                'name' => 'Ponte Phase 1',
                'code' => 'PONTE_PHASE1',
                'start_day' => 141,
                'end_day' => 280,
                'description' => 'Phase de ponte optimale (taux attendu : 95 %)',
                'min_temperature' => 18,
                'max_temperature' => 24,
                'humidity_min' => 50,
                'humidity_max' => 60,
                'light_hours' => 16,
                'density_per_m2' => 8,
                'feed_type' => 'Aliment ponte phase 1',
                'order' => 4,
            ],
            [
                'name' => 'Ponte Phase 2',
                'code' => 'PONTE_PHASE2',
                'start_day' => 281,
                'end_day' => 420,
                'description' => 'Phase de ponte prolongée (taux attendu : 85 %)',
                'min_temperature' => 18,
                'max_temperature' => 24,
                'humidity_min' => 50,
                'humidity_max' => 60,
                'light_hours' => 16,
                'density_per_m2' => 8,
                'feed_type' => 'Aliment ponte phase 2',
                'order' => 5,
            ],
            [
                'name' => 'Ponte Phase 3',
                'code' => 'PONTE_PHASE3',
                'start_day' => 421,
                'end_day' => 560,
                'description' => 'Phase de fin de ponte (taux attendu : 75 %)',
                'min_temperature' => 18,
                'max_temperature' => 24,
                'humidity_min' => 50,
                'humidity_max' => 60,
                'light_hours' => 16,
                'density_per_m2' => 8,
                'feed_type' => 'Aliment ponte fin de cycle',
                'order' => 6,
            ],
        ],
    ];

    /**
     * Rôles système créés automatiquement pour chaque nouvelle organisation.
     *
     * @var array<string, array{name: string, weight: int, permissions: array<string>}>
     */
    public const SYSTEM_ROLES = [
        'owner' => [
            'name' => 'Propriétaire',
            'weight' => 4,
            'permissions' => '*',
        ],
        'admin' => [
            'name' => 'Administrateur',
            'weight' => 3,
            'permissions' => [
                // Organisation & structure
                'members.view', 'members.create', 'members.update', 'members.delete',
                'farms.view', 'farms.create', 'farms.update', 'farms.delete',
                'buildings.view', 'buildings.create', 'buildings.update', 'buildings.delete',
                'roles.view', 'roles.create', 'roles.update',
                'settings.view', 'settings.update',
                // Élevage
                'flocks.view', 'flocks.create', 'flocks.update', 'flocks.delete',
                'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
                'production_phases.view', 'production_phases.create', 'production_phases.update', 'production_phases.delete',
                // Production & suivi
                'daily_records.view', 'daily_records.create', 'daily_records.update', 'daily_records.delete',
                'farm_logs.view', 'farm_logs.create', 'farm_logs.update', 'farm_logs.delete',
                // Santé
                'health_programs.view', 'health_programs.create', 'health_programs.update', 'health_programs.delete',
                'vaccinations.view', 'vaccinations.create', 'vaccinations.update', 'vaccinations.delete',
                'health_incidents.view', 'health_incidents.create', 'health_incidents.update', 'health_incidents.delete',
                'vet_visits.view', 'vet_visits.create', 'vet_visits.update', 'vet_visits.delete',
                'veterinarians.view', 'veterinarians.create', 'veterinarians.update', 'veterinarians.delete',
                // Équipements
                'equipments.view', 'equipments.create', 'equipments.update', 'equipments.delete',
                'equipments.move', 'equipments.maintenance', 'equipments.dispose',
                // Ressources Humaines
                'employees.view', 'employees.create', 'employees.update', 'employees.delete',
                'employees.terminate', 'employees.suspend', 'employees.reactivate',
                'contracts.view', 'contracts.create', 'contracts.update', 'contracts.delete',
                'contracts.renew',
                'leaves.view', 'leaves.create', 'leaves.update', 'leaves.delete',
                'leaves.approve', 'leaves.reject',
                'attendances.view', 'attendances.create', 'attendances.update', 'attendances.delete',
                'payrolls.view', 'payrolls.create', 'payrolls.update', 'payrolls.delete',
                'payrolls.validate', 'payrolls.mark_paid', 'payrolls.cancel',
                // Actions métier — Bandes
                'flocks.start', 'flocks.complete', 'flocks.cancel',
                // Actions métier — Journal de ferme
                'farm_logs.resolve', 'farm_logs.reopen',
                // Actions métier — Incidents sanitaires
                'health_incidents.start_treatment', 'health_incidents.start_monitoring', 'health_incidents.resolve',
                // Actions métier — Membres
                'members.reset_password', 'members.suspend',
                // Actions métier — Vaccinations
                'vaccinations.mark_done', 'vaccinations.reschedule', 'vaccinations.skip',
                // Actions métier — Visites vétérinaires
                'vet_visits.mark_paid', 'vet_visits.create_follow_up',
                // Comptabilité
                'accounts.view', 'accounts.create', 'accounts.update', 'accounts.delete',
                'accounts.set_default', 'accounts.recalculate_balance',
                'transactions.view', 'transactions.create', 'transactions.update', 'transactions.delete',
                'transactions.reconcile', 'transactions.reverse',
                'account_transfers.view', 'account_transfers.create', 'account_transfers.update', 'account_transfers.delete',
                'account_transfers.complete', 'account_transfers.cancel', 'account_transfers.reverse',
                'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
                'expenses.mark_paid', 'expenses.cancel',
                // Commercial
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                'sales.view', 'sales.create', 'sales.update', 'sales.delete',
                'sales.record_payment', 'sales.cancel',
                'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
                'quotes.convert',
                'payments.view', 'payments.create', 'payments.update', 'payments.delete',
                'credit_notes.view', 'credit_notes.create', 'credit_notes.update', 'credit_notes.delete',
                'credit_notes.apply', 'credit_notes.cancel',
                // Stocks
                'stock_categories.view', 'stock_categories.create', 'stock_categories.update', 'stock_categories.delete',
                'stocks.view', 'stocks.create', 'stocks.update', 'stocks.delete',
                'stocks.record_movement',
                'stock_lots.view', 'stock_lots.create', 'stock_lots.update', 'stock_lots.delete',
                'stock_movements.view', 'stock_movements.create', 'stock_movements.update', 'stock_movements.delete',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.delete',
                'purchase_orders.approve', 'purchase_orders.send', 'purchase_orders.receive',
                'stock_inventories.view', 'stock_inventories.create', 'stock_inventories.update', 'stock_inventories.delete',
                'stock_inventories.validate', 'stock_inventories.adjust',
            ],
        ],
    ];

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'country_id',
        'city_id',
        'logo_url',
        'description',
        'is_active',
        'plan_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organisation $organisation) {
            if (empty($organisation->slug)) {
                $organisation->slug = Str::slug($organisation->name);

                // Garantir l'unicité du slug
                $originalSlug = $organisation->slug;
                $counter = 1;
                while (static::where('slug', $organisation->slug)->exists()) {
                    $organisation->slug = $originalSlug . '-' . $counter++;
                }
            }
        });

        // Créer les rôles système et les phases de production par défaut après la création
        static::created(function (Organisation $organisation) {
            $organisation->createSystemRoles();
            $organisation->seedDefaultProductionPhases();
        });

        // Soft-delete en cascade sur les fermes
        static::deleting(function (Organisation $organisation) {
            if (! $organisation->isForceDeleting()) {
                $organisation->farms()->each(fn (Farm $farm) => $farm->delete());
            }
        });
    }

    public function getCurrentTenantLabel(): string
    {
        return 'Organisation active';
    }

    // ──────────────────────────────────────────────
    //  Relations
    // ──────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function organisationRoles(): HasMany
    {
        return $this->hasMany(OrganisationRole::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_users')
            ->using(OrganisationUser::class)
            ->withPivot(['id', 'organisation_role_id', 'permissions', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()
            ->wherePivot('status', MemberStatus::Active);
    }

    public function pendingInvitations(): BelongsToMany
    {
        return $this->members()
            ->wherePivot('status', MemberStatus::Invited);
    }

    public function owners(): BelongsToMany
    {
        return $this->activeMembers()
            ->whereHas('organisations', function (Builder $query) {
                $query->whereColumn('organisation_users.organisation_id', 'organisations.id')
                    ->whereIn('organisation_users.organisation_role_id', function ($sub) {
                        $sub->select('id')
                            ->from('organisation_roles')
                            ->whereColumn('organisation_roles.organisation_id', 'organisations.id')
                            ->where('slug', 'owner');
                    });
            });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }

    public function productionPhases(): HasMany
    {
        return $this->hasMany(ProductionPhase::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    // ──────────────────────────────────────────────
    //  Scopes
    // ──────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('members', fn (Builder $q) => $q->where('organisation_users.user_id', $userId)
            ->where('organisation_users.status', MemberStatus::Active)
        );
    }

    public function scopeWithActiveOwner(Builder $query): Builder
    {
        return $query->whereHas('owners');
    }

    // ──────────────────────────────────────────────
    //  Helpers rôles
    // ──────────────────────────────────────────────

    /**
     * Retrouver un rôle de cette organisation par slug.
     */
    public function findRoleBySlug(string $slug): ?OrganisationRole
    {
        return $this->organisationRoles()->where('slug', $slug)->first();
    }

    /**
     * Créer les phases de production par défaut pour cette organisation.
     * Appelé automatiquement après la création de l'organisation.
     */
    public function seedDefaultProductionPhases(): void
    {
        $chickenTypes = ChickenType::whereIn('name', array_keys(self::DEFAULT_PRODUCTION_PHASES))
            ->pluck('id', 'name');

        foreach (self::DEFAULT_PRODUCTION_PHASES as $chickenTypeName => $phases) {
            $chickenTypeId = $chickenTypes->get($chickenTypeName);

            if (! $chickenTypeId) {
                continue;
            }

            foreach ($phases as $phase) {
                ProductionPhase::firstOrCreate(
                    [
                        'organisation_id' => $this->id,
                        'chicken_type_id' => $chickenTypeId,
                        'code' => $phase['code'],
                    ],
                    array_merge($phase, [
                        'organisation_id' => $this->id,
                        'chicken_type_id' => $chickenTypeId,
                        'is_active' => true,
                    ]),
                );
            }
        }
    }

    /**
     * Créer les rôles système et assigner les permissions.
     */
    public function createSystemRoles(): void
    {
        $allPermissions = OrganisationPermission::pluck('id', 'slug');

        foreach (self::SYSTEM_ROLES as $slug => $data) {
            $role = $this->organisationRoles()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'weight' => $data['weight'],
                    'is_system' => true,
                ],
            );

            // Assigner les permissions
            if ($data['permissions'] === '*') {
                $role->permissions()->syncWithoutDetaching($allPermissions->values());
            } else {
                $permIds = $allPermissions->only($data['permissions'])->values();
                $role->permissions()->syncWithoutDetaching($permIds);
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Helpers plan
    // ──────────────────────────────────────────────

    /**
     * Vérifie si le plan de l'organisation inclut une fonctionnalité.
     */
    public function hasFeature(PlanFeature|string $feature): bool
    {
        return $this->plan?->hasFeature($feature) ?? false;
    }

    // ──────────────────────────────────────────────
    //  Helpers membres
    // ──────────────────────────────────────────────

    public function hasMember(User $user): bool
    {
        return $this->activeMembers()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function addMember(
        User $user,
        OrganisationRole|string $role,
        MemberStatus $status = MemberStatus::Active,
    ): void {
        $roleId = $role instanceof OrganisationRole
            ? $role->id
            : $this->findRoleBySlug($role)?->id;

        $this->members()->attach($user->id, [
            'organisation_role_id' => $roleId,
            'status' => $status,
            'joined_at' => $status === MemberStatus::Active ? now() : null,
        ]);
    }

    public function invite(
        User $user,
        OrganisationRole|string $role,
    ): void {
        $this->addMember($user, $role, MemberStatus::Invited);
    }

    public function acceptInvitation(User $user): bool
    {
        $pivot = $this->members()
            ->where('user_id', $user->id)
            ->wherePivot('status', MemberStatus::Invited)
            ->first()
            ?->pivot;

        if (! $pivot) {
            return false;
        }

        $pivot->update([
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        return true;
    }

    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    public function suspendMember(User $user): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'status' => MemberStatus::Suspended,
        ]);
    }

    public function changeMemberRole(User $user, OrganisationRole|string $role): void
    {
        $roleId = $role instanceof OrganisationRole
            ? $role->id
            : $this->findRoleBySlug($role)?->id;

        $this->members()->updateExistingPivot($user->id, [
            'organisation_role_id' => $roleId,
        ]);
    }

    public function activeMemberCount(): int
    {
        return $this->activeMembers()->count();
    }
}
