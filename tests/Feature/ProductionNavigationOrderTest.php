<?php

use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use App\Filament\Farm\Resources\FarmLogs\FarmLogResource;
use App\Filament\Farm\Resources\Flocks\FlockResource;
use App\Filament\Farm\Resources\HealthIncidents\HealthIncidentResource;
use App\Filament\Farm\Resources\Slaughters\SlaughterResource;
use App\Filament\Farm\Resources\Vaccinations\VaccinationResource;
use App\Filament\Farm\Resources\VetVisits\VetVisitResource;

/**
 * Ordre du menu « Production » du panel Farm, tel que voulu par l'exploitant :
 * Bandes, Suivi quotidien, Vaccinations, Visites vétérinaires, Incidents sanitaires, Abattages, Journal de ferme.
 */
it('présente le groupe Production dans l\'ordre du travail quotidien', function () {
    $resources = [
        FlockResource::class,
        DailyRecordResource::class,
        VaccinationResource::class,
        VetVisitResource::class,
        HealthIncidentResource::class,
        SlaughterResource::class,
        FarmLogResource::class,
    ];

    $sorted = collect($resources)->sortBy(fn (string $resource) => $resource::getNavigationSort())->values()->all();

    expect($sorted)->toBe($resources)
        ->and(collect($resources)->map(fn (string $resource) => $resource::getNavigationGroup())->unique()->all())->toBe(['Production'])
        ->and(collect($resources)->map(fn (string $resource) => $resource::getNavigationSort())->all())->toBe([1, 2, 3, 4, 5, 6, 7]);
});
