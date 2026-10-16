<?php

namespace App\Filament\Farm\Pages;

use App\Models\Organisation;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * Seule page accessible quand l'organisation est suspendue par la plateforme : explique la
 * situation et renvoie vers le support.
 */
class OrganisationSuspended extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'suspendu';

    protected static ?string $title = 'Organisation suspendue';

    protected string $view = 'filament.farm.pages.organisation-suspended';

    public ?Organisation $organisation = null;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->organisation = $tenant instanceof Organisation ? $tenant : null;
    }
}
