<?php

use App\Enums\PlanFeature;
use App\Filament\Farm\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Farm\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Farm\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Farm\Resources\Suppliers\Pages\ViewSupplier;
use App\Filament\Farm\Resources\Suppliers\SupplierResource;
use App\Models\City;
use App\Models\Country;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Plan;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->plan = Plan::factory()->create([
        'features' => [
            PlanFeature::MultiFarms->value,
            PlanFeature::Suppliers->value,
        ],
    ]);

    $this->organisation = Organisation::factory()->create([
        'plan_id' => $this->plan->id,
    ]);

    $this->ownerRole = $this->organisation->organisationRoles()
        ->where('slug', 'owner')->first();

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $this->ownerRole);

    $this->farm = Farm::factory()->create([
        'organisation_id' => $this->organisation->id,
    ]);

    // Authentifier l'utilisateur et configurer le panel Farm multi-tenant
    actingAs($this->user);
    Filament::setCurrentPanel(
        Filament::getPanel('farm'),
    );
    Filament::setTenant($this->organisation);
});

// ──────────────────────────────────────────────
//  Liste
// ──────────────────────────────────────────────

it('peut afficher la liste des fournisseurs', function () {
    Livewire::test(ListSuppliers::class)
        ->assertOk();
});

it('affiche les fournisseurs du tenant dans la table', function () {
    $supplier = Supplier::factory()->create([
        'farm_id' => $this->farm->id,
    ]);

    Livewire::test(ListSuppliers::class)
        ->assertCanSeeTableRecords([$supplier]);
});

// ──────────────────────────────────────────────
//  Scope tenant
// ──────────────────────────────────────────────

it('le scope tenant filtre les fournisseurs par organisation', function () {
    $supplier = Supplier::factory()->create([
        'farm_id' => $this->farm->id,
    ]);

    // Fournisseur d'une autre organisation
    $otherFarm = Farm::factory()->create();
    $otherSupplier = Supplier::factory()->create([
        'farm_id' => $otherFarm->id,
    ]);

    $scopedQuery = SupplierResource::scopeEloquentQueryToTenant(
        Supplier::query(),
        $this->organisation,
    );

    $results = $scopedQuery->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($supplier->id);
});

it('un membre restreint ne voit que les fournisseurs de ses fermes assignées', function () {
    // Créer un employé (weight=1, accès restreint aux fermes)
    $employeeRole = $this->organisation->organisationRoles()
        ->where('slug', 'employee')->first();

    $employee = User::factory()->create();
    $this->organisation->addMember($employee, $employeeRole);

    // Récupérer le membership directement via Eloquent (pas via pivot)
    $membership = OrganisationUser::query()
        ->where('user_id', $employee->id)
        ->where('organisation_id', $this->organisation->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->canAccessAllFarms())->toBeFalse();

    // Assigner l'employé à farm1 seulement
    $farm2 = Farm::factory()->create([
        'organisation_id' => $this->organisation->id,
    ]);

    $this->farm->assignedMembers()->attach($membership->id);

    $supplierFarm1 = Supplier::factory()->create(['farm_id' => $this->farm->id]);
    $supplierFarm2 = Supplier::factory()->create(['farm_id' => $farm2->id]);

    // Vérifier le scope directement via le mécanisme Farm::accessibleBy
    $accessibleFarms = Farm::query()
        ->where('organisation_id', $this->organisation->id)
        ->accessibleBy($membership)
        ->pluck('id');

    $results = Supplier::whereIn('farm_id', $accessibleFarms)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($supplierFarm1->id);
});

// ──────────────────────────────────────────────
//  Création
// ──────────────────────────────────────────────

it('peut créer un fournisseur', function () {
    $country = Country::factory()->create();
    $city = City::factory()->create(['country_id' => $country->id]);

    Livewire::test(CreateSupplier::class)
        ->fillForm([
            'farm_id' => $this->farm->id,
            'name' => 'Ivoire Aliments SA',
            'code' => 'SUP-TEST-001',
            'type' => 'aliment',
            'contact_name' => 'Kouamé Jean',
            'phone' => '+225 07 00 00 00',
            'email' => 'contact@ivoire-aliments.ci',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Supplier::class, [
        'code' => 'SUP-TEST-001',
        'name' => 'Ivoire Aliments SA',
        'type' => 'aliment',
        'farm_id' => $this->farm->id,
        'country_id' => $country->id,
        'city_id' => $city->id,
    ]);
});

it('valide les champs requis à la création', function (array $data, array $errors) {
    Livewire::test(CreateSupplier::class)
        ->fillForm([
            'farm_id' => $this->farm->id,
            'name' => 'Fournisseur Test',
            'type' => 'aliment',
            'is_active' => true,
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    'name requis' => [['name' => null], ['name' => 'required']],
    'farm_id requis' => [['farm_id' => null], ['farm_id' => 'required']],
    'type requis' => [['type' => null], ['type' => 'required']],
]);

it('valide l\'unicité du code fournisseur', function () {
    Supplier::factory()->create([
        'farm_id' => $this->farm->id,
        'code' => 'SUP-UNIQUE',
    ]);

    Livewire::test(CreateSupplier::class)
        ->fillForm([
            'farm_id' => $this->farm->id,
            'name' => 'Autre Fournisseur',
            'code' => 'SUP-UNIQUE',
            'type' => 'materiel',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
});

it('peut créer un fournisseur avec toutes les informations financières', function () {
    Livewire::test(CreateSupplier::class)
        ->fillForm([
            'farm_id' => $this->farm->id,
            'name' => 'Pharma Vet CI',
            'code' => 'SUP-FIN-001',
            'type' => 'medicament',
            'tax_id' => 'CI-1234567890',
            'bank_name' => 'SGBCI',
            'bank_account' => 'CI93 0000 0000 0000 0000 0000',
            'payment_terms' => '30 jours net',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Supplier::class, [
        'code' => 'SUP-FIN-001',
        'tax_id' => 'CI-1234567890',
        'bank_name' => 'SGBCI',
        'payment_terms' => '30 jours net',
    ]);
});

// ──────────────────────────────────────────────
//  Édition
// ──────────────────────────────────────────────

it('peut modifier un fournisseur', function () {
    $country = Country::factory()->create();
    $city = City::factory()->create(['country_id' => $country->id]);

    $supplier = Supplier::factory()->create([
        'farm_id' => $this->farm->id,
        'name' => 'Ancien Nom',
        'phone' => '+22507000000',
        'country_id' => $country->id,
        'city_id' => $city->id,
    ]);

    Livewire::test(EditSupplier::class, [
        'record' => $supplier->id,
    ])
        ->fillForm([
            'name' => 'Nouveau Nom SA',
            'type' => 'equipement',
            'phone' => '+225 05 11 22 33',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Supplier::class, [
        'id' => $supplier->id,
        'name' => 'Nouveau Nom SA',
        'type' => 'equipement',
        'phone' => '+225 05 11 22 33',
    ]);
});

it('peut désactiver un fournisseur', function () {
    $supplier = Supplier::factory()->create([
        'farm_id' => $this->farm->id,
        'phone' => '+22507000000',
        'city_id' => null,
        'country_id' => null,
        'is_active' => true,
    ]);

    Livewire::test(EditSupplier::class, [
        'record' => $supplier->id,
    ])
        ->fillForm([
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Supplier::class, [
        'id' => $supplier->id,
        'is_active' => false,
    ]);
});

// ──────────────────────────────────────────────
//  Vue
// ──────────────────────────────────────────────

it('peut afficher les détails d\'un fournisseur', function () {
    $supplier = Supplier::factory()->create([
        'farm_id' => $this->farm->id,
        'code' => 'SUP-VIEW',
        'name' => 'Fournisseur Vue SA',
        'type' => 'poussin',
    ]);

    Livewire::test(ViewSupplier::class, [
        'record' => $supplier->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'code' => 'SUP-VIEW',
            'name' => 'Fournisseur Vue SA',
        ]);
});

// ──────────────────────────────────────────────
//  Feature gating
// ──────────────────────────────────────────────

it('masque la navigation si le plan n\'a pas la feature Suppliers', function () {
    $planSansSuppliers = Plan::factory()->create([
        'features' => [PlanFeature::MultiFarms->value],
    ]);

    $org = Organisation::factory()->create([
        'plan_id' => $planSansSuppliers->id,
    ]);

    expect($org->hasFeature(PlanFeature::Suppliers))->toBeFalse();
});

it('bloque l\'accès si le plan n\'a pas la feature Suppliers', function () {
    $planSansSuppliers = Plan::factory()->create([
        'features' => [PlanFeature::MultiFarms->value],
    ]);

    $org = Organisation::factory()->create([
        'plan_id' => $planSansSuppliers->id,
    ]);

    $employee = User::factory()->create();
    $role = $org->organisationRoles()->where('slug', 'owner')->first();
    $org->addMember($employee, $role);

    actingAs($employee);
    Filament::setTenant($org);

    expect(SupplierResource::canAccess())->toBeFalse();
});

// ──────────────────────────────────────────────
//  Helpers & Scopes du modèle
// ──────────────────────────────────────────────

it('retourne les options de type', function () {
    $options = Supplier::typeOptions();

    expect($options)->toBeArray()
        ->toHaveCount(5)
        ->toHaveKeys(['aliment', 'medicament', 'poussin', 'materiel', 'equipement']);
});

it('retourne le label du type', function (string $type, string $expectedLabel) {
    $supplier = Supplier::factory()->make(['type' => $type]);

    expect($supplier->getTypeLabel())->toBe($expectedLabel);
})->with([
    'aliment' => ['aliment', 'Aliment'],
    'medicament' => ['medicament', 'Médicament'],
    'poussin' => ['poussin', 'Poussin'],
    'materiel' => ['materiel', 'Matériel'],
    'equipement' => ['equipement', 'Équipement'],
]);

it('identifie correctement le type de fournisseur', function (string $type, string $method) {
    $supplier = Supplier::factory()->make(['type' => $type]);

    expect($supplier->$method())->toBeTrue();
})->with([
    'aliment' => ['aliment', 'isAlimentSupplier'],
    'medicament' => ['medicament', 'isMedicamentSupplier'],
    'poussin' => ['poussin', 'isPoussinSupplier'],
    'materiel' => ['materiel', 'isMaterielSupplier'],
    'equipement' => ['equipement', 'isEquipementSupplier'],
]);

it('le scope active filtre les fournisseurs actifs', function () {
    $active = Supplier::factory()->create(['farm_id' => $this->farm->id, 'is_active' => true]);
    $inactive = Supplier::factory()->create(['farm_id' => $this->farm->id, 'is_active' => false]);

    $results = Supplier::active()->pluck('id');

    expect($results)->toContain($active->id)
        ->not->toContain($inactive->id);
});

it('le scope ofType filtre par type', function () {
    $aliment = Supplier::factory()->create(['farm_id' => $this->farm->id, 'type' => 'aliment']);
    $medicament = Supplier::factory()->create(['farm_id' => $this->farm->id, 'type' => 'medicament']);

    $results = Supplier::ofType('aliment')->pluck('id');

    expect($results)->toContain($aliment->id)
        ->not->toContain($medicament->id);
});

it('le scope forFarm filtre par ferme', function () {
    $otherFarm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    $supplierFarm1 = Supplier::factory()->create(['farm_id' => $this->farm->id]);
    $supplierFarm2 = Supplier::factory()->create(['farm_id' => $otherFarm->id]);

    $results = Supplier::forFarm($this->farm->id)->pluck('id');

    expect($results)->toContain($supplierFarm1->id)
        ->not->toContain($supplierFarm2->id);
});

// ──────────────────────────────────────────────
//  Relations
// ──────────────────────────────────────────────

it('un fournisseur appartient à une ferme', function () {
    $supplier = Supplier::factory()->create(['farm_id' => $this->farm->id]);

    expect($supplier->farm)->toBeInstanceOf(Farm::class)
        ->and($supplier->farm->id)->toBe($this->farm->id);
});

it('un fournisseur appartient à une ville et un pays', function () {
    $country = Country::factory()->create();
    $city = City::factory()->create(['country_id' => $country->id]);

    $supplier = Supplier::factory()->create([
        'farm_id' => $this->farm->id,
        'country_id' => $country->id,
        'city_id' => $city->id,
    ]);

    expect($supplier->city)->toBeInstanceOf(City::class)
        ->and($supplier->city->id)->toBe($city->id)
        ->and($supplier->country)->toBeInstanceOf(Country::class)
        ->and($supplier->country->id)->toBe($country->id);
});

it('un fournisseur peut ne pas avoir de ville ni de pays', function () {
    $supplier = Supplier::factory()->create([
        'farm_id' => $this->farm->id,
        'city_id' => null,
        'country_id' => null,
    ]);

    expect($supplier->city)->toBeNull()
        ->and($supplier->country)->toBeNull();
});

it('une ferme a plusieurs fournisseurs', function () {
    Supplier::factory()->count(3)->create(['farm_id' => $this->farm->id]);

    expect($this->farm->suppliers)->toHaveCount(3);
});

// ──────────────────────────────────────────────
//  Soft Delete
// ──────────────────────────────────────────────

it('un fournisseur supprimé est exclu des requêtes par défaut', function () {
    $supplier = Supplier::factory()->create(['farm_id' => $this->farm->id]);
    $supplier->delete();

    expect(Supplier::find($supplier->id))->toBeNull()
        ->and(Supplier::withTrashed()->find($supplier->id))->not->toBeNull();
});
