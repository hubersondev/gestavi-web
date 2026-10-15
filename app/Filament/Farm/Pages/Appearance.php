<?php

namespace App\Filament\Farm\Pages;

use App\Enums\UiTheme;
use App\Services\UserAppearance;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Apparence personnelle : thème et couleur principale, mémorisés sur l'utilisateur et appliqués
 * sur tous ses appareils. Accessible depuis le menu utilisateur, pas depuis la navigation.
 */
class Appearance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $title = 'Apparence';

    protected static ?string $slug = 'apparence';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.farm.pages.appearance';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $user = Filament::auth()->user();

        $this->form->fill([
            'ui_theme' => UserAppearance::themeFor($user)->value,
            'ui_primary_color' => UserAppearance::primaryKeyFor($user),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thème')
                    ->description('Le thème s\'applique à toutes vos sessions, sur tous vos appareils.')
                    ->components([
                        Radio::make('ui_theme')
                            ->label('')
                            ->options(UiTheme::class)
                            ->required(),
                    ]),
                Section::make('Couleur principale')
                    ->description('Boutons, liens, menu actif et indicateurs positifs.')
                    ->components([
                        Radio::make('ui_primary_color')
                            ->label('')
                            ->options(UserAppearance::primaryOptions())
                            ->inline()
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Filament::auth()->user()->forceFill([
            'ui_theme' => $state['ui_theme'] instanceof UiTheme ? $state['ui_theme'] : UiTheme::from((string) $state['ui_theme']),
            'ui_primary_color' => $state['ui_primary_color'],
        ])->save();

        Notification::make()->title('Apparence enregistrée')->success()->send();

        // Rechargement complet : la coque du panel (thème, couleurs) est rendue côté serveur
        $this->redirect(static::getUrl(), navigate: false);
    }
}
