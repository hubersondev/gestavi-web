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

// Vaccinations et traitements en retard (une alerte par organisation et par jour).
Schedule::command('gestavi:alert-overdue-vaccinations')
    ->dailyAt('07:15')
    ->runInBackground()
    ->withoutOverlapping();

// Factures échues non réglées.
Schedule::command('gestavi:alert-overdue-invoices')
    ->dailyAt('07:30')
    ->runInBackground()
    ->withoutOverlapping();

// Produits sous le seuil d'alerte.
Schedule::command('gestavi:alert-low-stock')
    ->dailyAt('07:45')
    ->runInBackground()
    ->withoutOverlapping();

// Bandes actives sans relevé quotidien pour la veille.
Schedule::command('gestavi:alert-missing-daily-records')
    ->dailyAt('08:00')
    ->runInBackground()
    ->withoutOverlapping();

// ─── Sauvegardes (spatie/laravel-backup) ──────────────────────────────────────

// Purge des anciennes sauvegardes selon la stratégie de rétention (config/backup.php).
Schedule::command('backup:clean')
    ->dailyAt('01:30')
    ->withoutOverlapping();

// Sauvegarde quotidienne de la base de données (pg_dump) sur le disque configuré.
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Vérifie l'âge et la taille de la dernière sauvegarde et notifie en cas d'anomalie.
Schedule::command('backup:monitor')
    ->dailyAt('08:30')
    ->withoutOverlapping();

// ─── Tableau de bord groupe ──────────────────────────────────────────────────

// Agrégats quotidiens par ferme (hier et aujourd'hui) pour la consolidation de direction.
Schedule::command('gestavi:aggregate-farm-metrics')
    ->dailyAt('23:30')
    ->runInBackground()
    ->withoutOverlapping();
