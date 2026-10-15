<?php

namespace App\Providers\Filament;

use App\Filament\Farm\Pages\Appearance;
use App\Filament\Farm\Pages\NotificationPreferences;
use App\Filament\Farm\Pages\Tenancy\EditOrganisationProfile;
use App\Http\Middleware\EnsurePlanFeature;
use App\Models\Organisation;
use App\Services\UserAppearance;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\View\View;

class FarmPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('farm')
            ->path('farm')
            ->viteTheme('resources/css/filament/farm/theme.css')
            ->login()
            ->passwordReset()
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: fn (): bool => Filament::auth()->user()?->mustUseTwoFactor() ?? false,
            )
            ->tenant(Organisation::class, slugAttribute: 'slug')
            ->tenantProfile(EditOrganisationProfile::class)
            // Charte GESTAVI, résolue par utilisateur à chaque requête (thème, couleur principale)
            ->colors(fn (): array => UserAppearance::panelColors())
            ->darkMode(fn (): bool => UserAppearance::currentIsDark(), isForced: fn (): bool => UserAppearance::currentIsDark())
            ->themeSwitcher(false)
            ->font('Figtree')
            // Barre latérale repliable sur les icônes seules (bouton dans l'en-tête, choix mémorisé par le navigateur)
            ->sidebarCollapsibleOnDesktop()
            ->userMenuItems([
                Action::make('appearance')
                    ->label('Apparence')
                    ->icon(Heroicon::OutlinedSwatch)
                    ->url(fn (): string => Appearance::getUrl()),
                Action::make('notifications')
                    ->label('Notifications')
                    ->icon(Heroicon::OutlinedBell)
                    ->url(fn (): string => NotificationPreferences::getUrl()),
            ])
            ->databaseNotifications()
            ->navigationGroups([
                NavigationGroup::make('Exploitation'),
                NavigationGroup::make('Production'),
                NavigationGroup::make('Comptabilité'),
                NavigationGroup::make('Commercial'),
                NavigationGroup::make('Stocks'),
                NavigationGroup::make('Équipements'),
                NavigationGroup::make('Ressources Humaines'),
                NavigationGroup::make('Paramètres')
                    ->collapsed(),
                NavigationGroup::make('Rapports'),
            ])
            ->discoverResources(in: app_path('Filament/Farm/Resources'), for: 'App\Filament\Farm\Resources')
            ->discoverPages(in: app_path('Filament/Farm/Pages'), for: 'App\Filament\Farm\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Farm/Widgets'), for: 'App\Filament\Farm\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->tenantMiddleware([
                EnsurePlanFeature::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ])
            // Exercice comptable actif : filtre d'affichage des journaux datés et défaut des dates
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('partials.fiscal-year-selector-hook'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('partials.farm-selector-hook'),
            )
            // Lien vers la page mobile « Saisie terrain » (PWA hors-ligne)
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('offline.terrain-link'),
            )
            // Manifest PWA + enregistrement du service worker
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('offline.pwa-head'),
            );
    }
}
