<?php

use App\Enums\PayrollStatus;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\OrganisationPermission;
use App\Models\Product;
use App\Services\PayrollService;
use Database\Seeders\ChickenTypeSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Database\Seeders\PayrollParameterSeeder;

/**
 * Remise à niveau d'une organisation créée avant un déploiement : rôles système,
 * permissions des nouveaux modules, référentiels par défaut, bulletins en brouillon.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->seed(ChickenTypeSeeder::class);
    $this->seed(PayrollParameterSeeder::class);

    $this->organisation = Organisation::factory()->create();
});

it('recrée les rôles système manquants et attribue les permissions des nouveaux modules', function () {
    // Organisation « ancienne » : rôle employé supprimé, permissions produits inconnues de l'admin, aucun produit
    $this->organisation->organisationRoles()->where('slug', 'employee')->delete();
    $admin = $this->organisation->organisationRoles()->where('slug', 'admin')->firstOrFail();
    $admin->permissions()->detach(OrganisationPermission::query()->where('group', 'products')->pluck('id'));
    Product::query()->where('organisation_id', $this->organisation->id)->delete();

    expect($this->organisation->organisationRoles()->count())->toBe(3)
        ->and($admin->permissions()->where('slug', 'products.view')->exists())->toBeFalse();

    $this->artisan('gestavi:tenants-upgrade', ['--organisation' => $this->organisation->id])->assertSuccessful();

    $this->organisation->refresh();

    expect($this->organisation->organisationRoles()->pluck('slug')->sort()->values()->all())->toBe(['admin', 'employee', 'manager', 'owner'])
        ->and($admin->fresh()->permissions()->where('slug', 'products.view')->exists())->toBeTrue()
        ->and($this->organisation->organisationRoles()->where('slug', 'owner')->first()->permissions()->count())->toBe(OrganisationPermission::query()->count())
        ->and($this->organisation->products()->count())->toBe(count(Product::DEFAULT_PRODUCTS))
        ->and($this->organisation->productionPhases()->count())->toBeGreaterThan(0);
});

it('est idempotente', function () {
    $this->artisan('gestavi:tenants-upgrade')->assertSuccessful();
    $snapshot = [
        $this->organisation->organisationRoles()->count(),
        $this->organisation->products()->count(),
        $this->organisation->productionPhases()->count(),
        OrganisationPermission::query()->count(),
    ];

    $this->artisan('gestavi:tenants-upgrade')->assertSuccessful();

    expect([
        $this->organisation->organisationRoles()->count(),
        $this->organisation->products()->count(),
        $this->organisation->productionPhases()->count(),
        OrganisationPermission::query()->count(),
    ])->toBe($snapshot);
});

it('recalcule les bulletins en brouillon sur demande, pas les bulletins validés', function () {
    $farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $employee = Employee::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $farm->id, 'fiscal_parts' => 1]);
    $contract = Contract::factory()->create(['employee_id' => $employee->id, 'base_salary' => 200_000, 'transport_allowance' => 0]);

    $service = app(PayrollService::class);
    $draft = $service->generatePayroll($employee, 2026, 8);
    $validated = $service->generatePayroll($employee, 2026, 9);
    $validated->update(['status' => PayrollStatus::Validated]);

    // Le contrat évolue après la génération : seul le brouillon doit suivre
    $contract->update(['base_salary' => 300_000]);

    $this->artisan('gestavi:tenants-upgrade', ['--organisation' => $this->organisation->id, '--regenerate-draft-payrolls' => true])
        ->expectsOutputToContain('1 bulletin(s) recalculé(s)')
        ->assertSuccessful();

    expect((float) $draft->fresh()->gross_salary)->toBe(300_000.0)
        ->and((float) $validated->fresh()->gross_salary)->toBe(200_000.0);
});
