<?php

use App\Enums\PlanFeature;
use App\Filament\Farm\Pages\GettingStarted;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Guide de démarrage : étapes ordonnées, état calculé, badge des étapes restantes.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');

    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('liste les étapes dans l\'ordre, marque celles qui sont faites et compte les obligatoires restantes', function () {
    $steps = collect(GettingStarted::stepsFor($this->organisation));

    expect($steps->pluck('key')->first())->toBe('farms')
        ->and($steps->firstWhere('key', 'farms')['done'])->toBeFalse()
        ->and($steps->firstWhere('key', 'farms')['url'])->not->toBeNull()
        ->and($steps->firstWhere('key', 'opening_balance')['optional'])->toBeTrue()
        ->and(GettingStarted::getNavigationBadge())->toBe('6');

    Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    expect(collect(GettingStarted::stepsFor($this->organisation))->firstWhere('key', 'farms')['done'])->toBeTrue()
        ->and(GettingStarted::getNavigationBadge())->toBe('5');

    Livewire::test(GettingStarted::class)
        ->assertOk()
        ->assertSee('Mise en route de '.$this->organisation->name)
        ->assertSee('Créer vos fermes')
        ->assertSee('Ouvrir les exercices comptables')
        ->assertSee('Facultatif');
});

it('n\'offre pas de lien de création à un membre sans permission', function () {
    $employee = User::factory()->create();
    $this->organisation->addMember($employee, 'employee');
    actingAs($employee);

    $steps = collect(GettingStarted::stepsFor($this->organisation));

    expect($steps->firstWhere('key', 'farms')['url'])->toBeNull();
});
