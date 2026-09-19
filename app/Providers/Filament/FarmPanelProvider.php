<?php

namespace App\Providers\Filament;

use App\Filament\Farm\Pages\Tenancy\EditOrganisationProfile;
use App\Http\Middleware\EnsurePlanFeature;
use App\Models\Organisation;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FarmPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('farm')
            ->path('farm')
            ->login()
            ->passwordReset()
            ->tenant(Organisation::class, slugAttribute: 'slug')
            ->tenantProfile(EditOrganisationProfile::class)
            ->colors([
                'primary' => Color::Green,
                'danger' => Color::Rose,
                'warning' => Color::Orange,
                'success' => Color::Emerald,
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
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): \Illuminate\View\View => view('partials.farm-selector-hook'),
            );
    }
}
