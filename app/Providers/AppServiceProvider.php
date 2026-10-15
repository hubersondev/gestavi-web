<?php

namespace App\Providers;

use App\Services\CurrentFiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Mémoire d'une requête (réinitialisée par requête sous Octane) : exercices de l'organisation courante
        $this->app->scoped(CurrentFiscalYear::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API mobile : 120 requêtes par minute par jeton (ou adresse IP), 5 demandes de jeton par minute et par adresse
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
        RateLimiter::for('api-token', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
