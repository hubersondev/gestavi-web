<?php

use App\Enums\UiTheme;
use App\Filament\Farm\Pages\Appearance;
use App\Models\Organisation;
use App\Models\User;
use App\Services\UserAppearance;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Apparence par utilisateur : thème et couleur principale mémorisés en base et appliqués au panel.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');

    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('applique Sous-bois et le vert GESTAVI par défaut, sans sélecteur clair/sombre', function () {
    $panel = Filament::getPanel('farm');

    expect(UserAppearance::themeFor($this->owner))->toBe(UiTheme::SousBois)
        ->and($panel->hasDarkMode())->toBeFalse()
        ->and($panel->hasDarkModeForced())->toBeFalse()
        ->and($panel->hasThemeSwitcher())->toBeFalse()
        ->and($panel->getColors()['primary'])->toBe(Color::hex('#15803d'))
        ->and($panel->getColors()['gray'])->toBe(UserAppearance::GRAY)
        ->and($panel->getFontFamily())->toBe('Figtree')
        ->and($panel->isSidebarCollapsibleOnDesktop())->toBeTrue();
});

it('enregistre le thème Nuit et une couleur principale depuis la page Apparence, puis force le mode sombre', function () {
    Livewire::test(Appearance::class)
        ->assertOk()
        ->assertFormSet(['ui_theme' => UiTheme::SousBois, 'ui_primary_color' => 'vert'])
        ->fillForm(['ui_theme' => 'nuit', 'ui_primary_color' => 'lagune'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Apparence enregistrée')
        ->assertRedirect();

    $this->owner->refresh();

    expect($this->owner->ui_theme)->toBe(UiTheme::Nuit)
        ->and($this->owner->ui_primary_color)->toBe('lagune');

    $panel = Filament::getPanel('farm');

    expect($panel->hasDarkMode())->toBeTrue()
        ->and($panel->hasDarkModeForced())->toBeTrue()
        ->and($panel->getColors()['primary'])->toBe(Color::hex('#0e7490'));
});

it('ignore une clé de couleur inconnue et retombe sur le vert GESTAVI', function () {
    $this->owner->forceFill(['ui_primary_color' => 'fuchsia'])->save();

    expect(UserAppearance::primaryKeyFor($this->owner->fresh()))->toBe('vert');
});
