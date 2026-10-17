<?php

use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\Offline\FieldEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// ─── Saisie terrain hors-ligne (PWA) ─────────────────────────────────────────

Route::get('/offline/assets.json', [FieldEntryController::class, 'assets'])->name('offline.assets');

// Point d'entrée de la PWA installée : redirige vers la dernière page terrain ouverte (localStorage).
Route::view('/offline/start', 'offline.start')->name('offline.start');

// Exports de rapports sur disque local : lien signé, personnel, valable 24 h
Route::get('/exports/{path}', ExportDownloadController::class)
    ->where('path', '.*')
    ->middleware(['auth', 'signed'])
    ->name('exports.download');

Route::middleware(['auth', 'offline.member'])
    ->prefix('farm/{organisation:slug}/terrain')
    ->name('offline.')
    ->group(function (): void {
        Route::get('/', [FieldEntryController::class, 'page'])->name('page');
        Route::get('/api/flocks', [FieldEntryController::class, 'flocks'])->name('flocks');
        Route::get('/api/vaccinations', [FieldEntryController::class, 'vaccinations'])->name('vaccinations');
        Route::get('/api/csrf', [FieldEntryController::class, 'csrf'])->name('csrf');
        Route::post('/api/sync', [FieldEntryController::class, 'sync'])->name('sync');
    });
