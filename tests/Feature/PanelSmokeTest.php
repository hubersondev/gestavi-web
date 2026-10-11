<?php

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

/**
 * Test de fumée des panels : chaque page est ouverte par une vraie requête HTTP,
 * panel Filament démarré, middlewares et scopes globaux de tenant actifs. C'est la
 * seule façon d'attraper les erreurs qui n'apparaissent qu'à l'exécution des pages
 * (colonnes ambiguës, imports manquants, vues cassées, permissions incohérentes).
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->organisation = Organisation::query()->where('email', 'contact@ferme-demo.ci')->firstOrFail();
    $this->organisation->update(['plan_id' => Plan::query()->where('slug', 'enterprise')->value('id')]);

    $this->owner = User::query()->where('email', 'jean@ferme-demo.ci')->firstOrFail();
    $this->employee = User::query()->where('email', 'ama@ferme-demo.ci')->firstOrFail();
    $this->superAdmin = User::query()->where('email', 'superadmin@gestavi.com')->firstOrFail();

    // Les rôles à accès global doivent avoir configuré leur 2FA pour naviguer
    $this->owner->saveAppAuthenticationSecret('SMOKE-TEST-SECRET');
    $this->superAdmin->saveAppAuthenticationSecret('SMOKE-TEST-SECRET');
});

/**
 * Routes GET nommées d'un panel sans paramètre d'enregistrement (index, création, pages, profil).
 *
 * @return array<string, string> nom de route => URL
 */
function panelRoutesWithoutRecord(string $panelId, ?Organisation $tenant): array
{
    $urls = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var RoutingRoute $route */
        $name = (string) $route->getName();

        // Les routes d'authentification et le contrôleur de redirection vers le tenant ne sont pas des pages
        if (! str_starts_with($name, "filament.{$panelId}.") || str_contains($name, '.auth.') || $name === "filament.{$panelId}.tenant" || ! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $parameters = array_diff($route->parameterNames(), ['tenant']);

        if ($parameters !== []) {
            continue;
        }

        $urls[$name] = route($name, $tenant ? ['tenant' => $tenant] : []);
    }

    ksort($urls);

    return $urls;
}

/**
 * Pages d'enregistrement (view, edit…) de chaque ressource du panel, avec un enregistrement
 * visible du tenant : celui des données de démonstration, sinon un créé par factory.
 *
 * @return array{urls: array<string, string>, uncovered: list<string>}
 */
function panelRecordRoutes(Panel $panel, ?Organisation $tenant): array
{
    $urls = [];
    $uncovered = [];

    /** @var class-string<resource> $resource */
    foreach ($panel->getResources() as $resource) {
        $routeBase = $resource::getRouteBaseName($panel);

        $recordPages = array_values(array_filter(
            array_keys($resource::getPages()),
            fn (string $page): bool => in_array('record', Route::getRoutes()->getByName("{$routeBase}.{$page}")?->parameterNames() ?? [], true),
        ));

        if ($recordPages === []) {
            continue;
        }

        $record = smokeRecordFor($resource, $tenant);

        if (! $record) {
            $uncovered[] = class_basename($resource);

            continue;
        }

        foreach ($recordPages as $page) {
            $urls[class_basename($resource).'.'.$page] = $resource::getUrl($page, ['record' => $record], tenant: $tenant);
        }
    }

    ksort($urls);

    return ['urls' => $urls, 'uncovered' => $uncovered];
}

/**
 * @param  class-string<resource>  $resource
 */
function smokeRecordFor(string $resource, ?Organisation $tenant): ?Model
{
    $model = $resource::getModel();

    // Le scope global de tenant n'est posé qu'au démarrage du panel : on cloisonne explicitement,
    // sinon la recherche remonte des enregistrements d'autres organisations créés par les factories
    $scopedQuery = fn () => $tenant
        ? $resource::scopeEloquentQueryToTenant($resource::getEloquentQuery(), $tenant)
        : $resource::getEloquentQuery();

    $existing = $scopedQuery()->first();

    if ($existing) {
        return $existing;
    }

    if (! method_exists($model, 'factory')) {
        return null;
    }

    $table = (new $model)->getTable();
    $attributes = [];

    if ($tenant && Schema::hasColumn($table, 'organisation_id')) {
        $attributes['organisation_id'] = $tenant->id;
    }

    if ($tenant && Schema::hasColumn($table, 'farm_id')) {
        $attributes['farm_id'] = $tenant->farms()->value('id');
    }

    // Savepoint : une factory incompatible (contraintes, organisation) ne doit pas avorter la transaction du test
    try {
        DB::transaction(fn () => $model::factory()->create($attributes));
    } catch (Throwable) {
        return null;
    }

    return $scopedQuery()->first();
}

/**
 * Ouvre chaque URL et retourne les échecs sous forme lisible.
 *
 * @param  array<string, string>  $urls
 * @param  list<int>  $acceptedStatuses
 * @return list<string>
 */
function visitAll(array $urls, array $acceptedStatuses, callable $get): array
{
    $failures = [];

    // Livewire remplace le redirecteur du conteneur pendant le cycle de vie d'un composant et ne le
    // restaure qu'au dehydrate ; une page qui aborte (403, 404) le laisse détourné pour les requêtes
    // suivantes du même processus. Sans incidence en production (un processus par requête), mais à
    // neutraliser ici.
    $redirector = app('redirect');

    foreach ($urls as $name => $url) {
        // Chaque page tourne dans un savepoint : une requête SQL en erreur n'avorte pas la transaction du test
        DB::beginTransaction();

        try {
            /** @var TestResponse $response */
            $response = $get($url);
        } finally {
            DB::rollBack();
            app()->instance('redirect', $redirector);
        }

        $status = $response->getStatusCode();

        if (in_array($status, $acceptedStatuses, true)) {
            continue;
        }

        $exception = $response->exception;
        $detail = $exception
            ? ' — '.$exception::class.' : '.mb_substr($exception->getMessage(), 0, 300)
            : ($status === 302 ? ' — redirection vers '.$response->headers->get('Location') : '');

        $failures[] = sprintf('%s [%s] → HTTP %d%s', $name, $url, $status, $detail);
    }

    return $failures;
}

it('le propriétaire ouvre toutes les pages du panel ferme sans erreur', function () {
    actingAs($this->owner);

    $urls = panelRoutesWithoutRecord('farm', $this->organisation);
    expect($urls)->not->toBeEmpty();

    $failures = visitAll($urls, [200], fn (string $url) => $this->get($url));

    expect($failures)->toBe([], "Pages en erreur (propriétaire) :\n".implode("\n", $failures));
});

it('le propriétaire ouvre toutes les pages d\'enregistrement du panel ferme sans erreur', function () {
    actingAs($this->owner);
    $panel = Filament::getPanel('farm');
    Filament::setCurrentPanel($panel);
    Filament::setTenant($this->organisation);

    ['urls' => $urls, 'uncovered' => $uncovered] = panelRecordRoutes($panel, $this->organisation);
    expect($urls)->not->toBeEmpty();

    $failures = visitAll($urls, [200], fn (string $url) => $this->get($url));

    expect($failures)->toBe([], "Pages d'enregistrement en erreur (propriétaire) :\n".implode("\n", $failures));

    // Les ressources sans enregistrement disponible sont listées pour information, pas en échec
    if ($uncovered !== []) {
        fwrite(STDERR, "\nRessources sans enregistrement de test : ".implode(', ', $uncovered)."\n");
    }
});

it('l\'employé restreint ouvre chaque page ou se voit refuser l\'accès proprement', function () {
    actingAs($this->employee);

    $urls = panelRoutesWithoutRecord('farm', $this->organisation);

    // Filament masque le profil du tenant (404) à qui ne peut pas modifier l'organisation
    unset($urls['filament.farm.tenant.profile']);

    // 200 : autorisé ; 403 : refus propre par les permissions ; jamais 500 ni redirection inattendue
    $failures = visitAll($urls, [200, 403], fn (string $url) => $this->get($url));

    expect($failures)->toBe([], "Pages en erreur (employé) :\n".implode("\n", $failures));

    expect($this->get(route('filament.farm.tenant.profile', ['tenant' => $this->organisation]))->getStatusCode())->toBe(404);
});

it('le super-administrateur ouvre toutes les pages du panel admin sans erreur', function () {
    actingAs($this->superAdmin);
    $panel = Filament::getPanel('admin');
    Filament::setCurrentPanel($panel);

    $urls = panelRoutesWithoutRecord('admin', null);
    expect($urls)->not->toBeEmpty();

    $failures = visitAll($urls, [200], fn (string $url) => $this->get($url));

    expect($failures)->toBe([], "Pages en erreur (admin) :\n".implode("\n", $failures));

    ['urls' => $recordUrls] = panelRecordRoutes($panel, null);

    $recordFailures = visitAll($recordUrls, [200], fn (string $url) => $this->get($url));

    expect($recordFailures)->toBe([], "Pages d'enregistrement en erreur (admin) :\n".implode("\n", $recordFailures));
});
