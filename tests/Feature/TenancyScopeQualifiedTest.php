<?php

use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Filament\Farm\Pages\Reports\CommercialReportPage;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;

/**
 * Filament ajoute un scope global de tenant à chaque modèle de ressource : ce scope
 * doit qualifier `organisation_id` par sa table, sinon toute requête qui joint `farms`
 * (comparaisons inter-fermes des rapports) échoue sur une colonne ambiguë.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $ownerRole = $this->organisation->organisationRoles()->where('slug', 'owner')->first();

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $ownerRole);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    actingAs($this->user);
    $this->panel = Filament::getPanel('farm');
    Filament::setCurrentPanel($this->panel);
    Filament::setTenant($this->organisation);
});

afterEach(function () {
    // Les scopes globaux enregistrés par Filament sont statiques : on les retire pour les autres tests
    Model::clearBootedModels();
});

/**
 * @return list<class-string<resource>>
 */
function farmResourcesScopedToTenant(): array
{
    return collect(File::glob(app_path('Filament/Farm/Resources/*/*Resource.php')))
        ->map(fn (string $path): string => 'App\\Filament\\Farm\\Resources\\'.str_replace(['/', '.php'], ['\\', ''], mb_substr($path, mb_strlen(app_path('Filament/Farm/Resources/')))))
        ->filter(fn (string $class): bool => is_subclass_of($class, Resource::class) && (new ReflectionMethod($class, 'scopeEloquentQueryToTenant'))->getDeclaringClass()->getName() === $class)
        ->values()
        ->all();
}

it('les scopes tenant de toutes les ressources survivent à une jointure sur farms', function () {
    $resources = farmResourcesScopedToTenant();
    expect($resources)->not->toBeEmpty();

    foreach ($resources as $resource) {
        $resource::registerTenancyModelGlobalScope($this->panel);

        $model = $resource::getModel();
        $table = (new $model)->getTable();

        if (! Schema::hasColumn($table, 'organisation_id')) {
            continue;
        }

        // Une colonne organisation_id non qualifiée dans le scope rend cette requête ambiguë
        $count = $model::query()
            ->join('farms', 'farms.organisation_id', '=', "{$table}.organisation_id")
            ->count();

        expect($count)->toBeInt();
    }
});

it('la comparaison inter-fermes du rapport commercial fonctionne avec le scope tenant de Filament', function () {
    SaleResource::registerTenancyModelGlobalScope($this->panel);

    Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString(), 'status' => SaleStatus::Pending, 'total' => 1000, 'paid' => 0, 'balance' => 1000]);

    $page = app(CommercialReportPage::class);
    $page->filters = ['date_from' => now()->subDays(5)->toDateString(), 'date_to' => now()->toDateString()];

    $comparison = $page->getFarmComparison();

    expect($comparison)->toHaveCount(1)
        ->and($comparison->first()->farm_id)->toBe($this->farm->id)
        ->and($comparison->first()->total_ca)->toBe(1000.0);
});
