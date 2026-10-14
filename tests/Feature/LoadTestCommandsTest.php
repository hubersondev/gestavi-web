<?php

use App\Models\DailyRecord;
use App\Models\FarmDailyMetric;
use App\Models\Flock;
use App\Models\JournalEntry;
use App\Models\Organisation;
use App\Models\Sale;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\ChickenTypeSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Banc de charge : le générateur produit une base cohérente et la mesure ouvre les pages du panel.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->seed(ChartAccountSeeder::class);
    $this->seed(ChickenTypeSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('génère un jeu de données cohérent à petite échelle puis chronomètre les traitements', function () {
    $this->artisan('gestavi:load-dataset', ['--organisations' => 2, '--farms' => 2, '--buildings' => 1, '--days' => 30, '--accounting-sample' => 1])
        ->expectsOutputToContain('Traitements de fond chronométrés')
        ->assertSuccessful();

    $organisations = Organisation::query()->where('name', 'like', 'Ferme de charge %')->get();

    expect($organisations)->toHaveCount(2)
        ->and($organisations->first()->farms()->count())->toBe(2)
        ->and($organisations->first()->organisationRoles()->count())->toBeGreaterThanOrEqual(4)
        ->and(Flock::query()->where('organisation_id', $organisations->first()->id)->count())->toBe(4)
        ->and(DailyRecord::query()->where('organisation_id', $organisations->first()->id)->count())->toBeGreaterThan(50)
        ->and(Sale::query()->where('organisation_id', $organisations->first()->id)->count())->toBeGreaterThan(0)
        ->and(JournalEntry::query()->where('organisation_id', $organisations->first()->id)->count())->toBeGreaterThan(0)
        ->and(FarmDailyMetric::query()->count())->toBeGreaterThan(0);

    // Les séquences sont réalignées : une création Eloquent après les insertions massives ne collisionne pas
    $farm = $organisations->first()->farms()->create(['code' => 'APRES', 'name' => 'Ferme créée après', 'is_active' => true]);
    expect($farm->id)->toBeGreaterThan(0);
});

it('mesure les pages du panel pour l\'organisation la plus grande et écrit un rapport', function () {
    Storage::fake('local');

    $this->artisan('gestavi:load-dataset', ['--organisations' => 1, '--farms' => 1, '--buildings' => 1, '--days' => 10, '--accounting-sample' => 0, '--skip-jobs' => true])
        ->assertSuccessful();

    $this->artisan('gestavi:load-measure', ['--limit' => 4, '--rounds' => 1])
        ->expectsOutputToContain('Requêtes SQL les plus coûteuses')
        ->expectsOutputToContain('Rapport :')
        ->assertSuccessful();

    $reports = Storage::disk('local')->files('load-test');

    expect($reports)->toHaveCount(1)
        ->and(Storage::disk('local')->get($reports[0]))->toContain('| Page | HTTP |')->toContain('| 200 |');
});
