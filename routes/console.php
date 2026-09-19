<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Tâches planifiées ────────────────────────────────────────────────────────

// Marque quotidiennement les lots de stock dont la péremption est dépassée.
Schedule::command('gestavi:expire-stock-lots')
    ->dailyAt('03:30')
    ->runInBackground()
    ->withoutOverlapping();

// Notifie les admins des lots qui vont périmer sous 7 jours.
Schedule::command('gestavi:alert-expiring-stock-lots')
    ->dailyAt('07:00')
    ->runInBackground()
    ->withoutOverlapping();
