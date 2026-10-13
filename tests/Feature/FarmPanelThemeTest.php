<?php

use Filament\Facades\Filament;

/**
 * Le panel Farm sert un thème compilé par Vite dont les sources couvrent les vues personnalisées :
 * sans lui, les classes utilitaires Tailwind des rapports et du tableau de bord groupe sont absentes
 * du CSS et les pages s'affichent sans mise en page.
 */
it('enregistre un thème Vite sur le panel Farm couvrant les vues personnalisées', function () {
    $themePath = 'resources/css/filament/farm/theme.css';

    expect(Filament::getPanel('farm')->getViteTheme())->toBe($themePath);

    $css = file_get_contents(base_path($themePath));

    expect($css)
        ->toContain("@import '../../../../vendor/filament/filament/resources/css/theme.css';")
        ->toContain("@source '../../../../app/Filament/Farm/**/*';")
        ->toContain("@source '../../../../resources/views/filament/farm/**/*';");

    expect(file_get_contents(base_path('vite.config.js')))->toContain($themePath);
});
