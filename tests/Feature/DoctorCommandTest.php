<?php

use App\Console\Commands\Doctor;
use App\Models\Organisation;
use Database\Seeders\OrganisationPermissionSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * Diagnostic d'exploitation : chaque prérequis est contrôlé et l'état général est fidèle.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    Cache::forget(Doctor::SCHEDULER_HEARTBEAT_KEY);
});

it('signale la file synchrone, le planificateur muet et une organisation à remettre à niveau, sans échouer en développement', function () {
    config()->set('queue.default', 'sync');
    $organisation = Organisation::factory()->create();
    $organisation->organisationRoles()->where('slug', 'employee')->delete();

    $this->artisan('gestavi:doctor')
        ->expectsOutputToContain('QUEUE_CONNECTION=sync')
        ->expectsOutputToContain('aucun battement enregistré')
        ->expectsOutputToContain('gestavi:tenants-upgrade')
        ->expectsOutputToContain('0 en échec')
        ->assertSuccessful();
});

it('est satisfait du planificateur, de la file et des organisations quand tout est en place', function () {
    config()->set('queue.default', 'database');
    Cache::put(Doctor::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String());
    Organisation::factory()->create();

    $this->artisan('gestavi:doctor')
        ->expectsOutputToContain('dernier battement')
        ->expectsOutputToContain('organisation(s) au niveau du code')
        ->expectsOutputToContain('aucune en attente')
        ->assertSuccessful();
});

it('échoue hors développement quand le mode debug est actif ou la file synchrone', function () {
    config()->set('app.env', 'production');
    config()->set('app.debug', true);
    config()->set('queue.default', 'sync');

    $this->artisan('gestavi:doctor')
        ->expectsOutputToContain('APP_DEBUG=true')
        ->expectsOutputToContain('règles strictes')
        ->assertFailed();
});
