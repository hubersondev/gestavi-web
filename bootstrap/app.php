<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'plan.feature' => \App\Http\Middleware\EnsureApiPlanFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Remontée des erreurs vers Sentry (inactif tant que SENTRY_LARAVEL_DSN est vide)
        Integration::handles($exceptions);
    })->create();
