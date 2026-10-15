<?php

use App\Http\Middleware\EnsureApiPlanFeature;
use App\Http\Middleware\EnsureOfflineMember;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'plan.feature' => EnsureApiPlanFeature::class,
            'offline.member' => EnsureOfflineMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Remontée des erreurs vers Sentry (inactif tant que SENTRY_LARAVEL_DSN est vide)
        Integration::handles($exceptions);
    })->create();
