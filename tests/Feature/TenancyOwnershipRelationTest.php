<?php

use App\Enums\PaymentMethod;
use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Filament\Farm\Resources\Sales\Pages\ViewSale;
use App\Models\Account;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Services\SaleService;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Au démarrage du panel Ferme, Filament rattache chaque enregistrement créé au tenant courant par
 * la relation `organisation` du modèle de chaque ressource : un modèle sans cette relation fait
 * échouer toute création depuis l'interface (« does not have a relationship named [organisation] »),
 * y compris les créations indirectes (paiement → transaction). Régression du 19/10/2026.
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

afterEach(function () {
    // Les écouteurs et scopes enregistrés par le démarrage du panel sont statiques
    Model::clearBootedModels();
});

it('chaque modèle de ressource du panel Ferme expose la relation organisation attendue par Filament', function () {
    $resources = collect(File::glob(app_path('Filament/Farm/Resources/*/*Resource.php')))
        ->map(fn (string $path): string => 'App\\Filament\\Farm\\Resources\\'.str_replace(['/', '.php'], ['\\', ''], mb_substr($path, mb_strlen(app_path('Filament/Farm/Resources/')))))
        ->filter(fn (string $class): bool => is_subclass_of($class, Resource::class) && $class::isScopedToTenant());

    expect($resources)->not->toBeEmpty();

    foreach ($resources as $resource) {
        $model = $resource::getModel();

        expect($resource::getTenantOwnershipRelationship(new $model))
            ->toBeInstanceOf(BelongsTo::class, "{$model} ({$resource}) n'a pas de relation organisation");
    }
});

it('enregistre le paiement d\'une facture avec le panel réellement démarré', function () {
    Filament::bootCurrentPanel();

    $account = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'initial_balance' => 0, 'current_balance' => 0]);
    $sale = app(SaleService::class)->createSale(
        data: ['organisation_id' => $this->organisation->id, 'sale_date' => now()->toDateString()],
        items: [['product_name' => 'Poulets', 'unit' => 'unité', 'quantity' => 10, 'unit_price' => 3500]],
    );

    Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])
        ->callAction('recordPayment', data: [
            'amount' => 35000,
            'method' => PaymentMethod::Cash->value,
            'account_id' => $account->id,
            'payment_date' => now()->toDateString(),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Paiement enregistré');

    expect($sale->fresh()->status)->toBe(SaleStatus::Paid)
        ->and($sale->payments()->sole()->organisation_id)->toBe($this->organisation->id)
        ->and((float) $account->fresh()->current_balance)->toBe(35000.0);
});
