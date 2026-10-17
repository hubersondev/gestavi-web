<?php

use App\Enums\MemberStatus;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Sale;
use App\Models\User;
use App\Policies\SalePolicy;
use App\Services\MembershipResolver;
use Database\Seeders\OrganisationPermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Cache d'appartenance par requête : une seule lecture par utilisateur et organisation, rôle et
 * permissions compris, partagée entre toutes les instances de User ; invalidation à l'écriture.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, 'manager');
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    app(MembershipResolver::class)->flush();
});

function countQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('ne lit l\'appartenance qu\'une fois par requête, rôle et permissions compris, pour toutes les instances de l\'utilisateur', function () {
    $first = User::query()->find($this->user->id);
    $second = User::query()->find($this->user->id);

    $queries = countQueries(function () use ($first, $second): void {
        expect($first->membershipIn($this->organisation))->not->toBeNull()
            ->and($first->hasOrganisationPermission($this->organisation, 'flocks.view'))->toBeTrue()
            ->and($second->membershipIn($this->organisation)?->id)->toBe($first->membershipIn($this->organisation)->id)
            ->and($second->roleInOrganisation($this->organisation)?->slug)->toBe('manager')
            ->and($first->canAccessTenant($this->organisation))->toBeTrue();
    });

    // Une requête pour l'appartenance, une pour le rôle, une pour ses permissions
    expect($queries)->toBe(3);
});

it('la policy vérifie l\'appartenance sans requête supplémentaire', function () {
    $sale = Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id]);
    $this->user->membershipIn($this->organisation);

    $queries = countQueries(fn () => expect((new SalePolicy)->view($this->user, $sale))->toBeTrue());

    expect($queries)->toBe(0);
});

it('oublie l\'appartenance quand elle change ou disparaît', function () {
    $membership = $this->user->membershipIn($this->organisation);
    expect($membership->status)->toBe(MemberStatus::Active);

    $membership->update(['status' => MemberStatus::Suspended]);

    expect($this->user->membershipIn($this->organisation)?->status)->toBe(MemberStatus::Suspended)
        ->and($this->user->canAccessTenant($this->organisation))->toBeFalse();

    $membership->delete();

    expect($this->user->membershipIn($this->organisation))->toBeNull()
        ->and($this->user->canAccessTenant($this->organisation))->toBeFalse();
});

it('isole les utilisateurs et les organisations entre eux', function () {
    $other = Organisation::factory()->create();
    $stranger = User::factory()->create();

    expect($this->user->membershipIn($other))->toBeNull()
        ->and($stranger->membershipIn($this->organisation))->toBeNull()
        ->and($this->user->membershipIn($this->organisation))->not->toBeNull();
});
