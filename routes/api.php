<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DailyRecordController;
use App\Http\Controllers\Api\V1\FarmController;
use App\Http\Controllers\Api\V1\FlockController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Webhooks\MobileMoneyWebhookController;
use Illuminate\Support\Facades\Route;

// Webhooks opérateurs mobile money : corps signé HMAC-SHA256 (voir config/mobilemoney.php)
Route::post('/webhooks/mobile-money/{operator}', MobileMoneyWebhookController::class)
    ->where('operator', 'orange|mtn|moov|wave')
    ->name('webhooks.mobile-money');

// ─── API v1 — application mobile ─────────────────────────────────────────────
// Jetons Sanctum par appareil ; organisation désignée par son slug ; appartenance active vérifiée
// par `offline.member` (le même middleware que la page terrain) ; cloisonnement par ferme et
// permissions d'organisation identiques à ceux du panel. Débit : 120 appels/min par jeton.
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/token', [AuthController::class, 'token'])->middleware('throttle:api-token')->name('auth.token');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::delete('/auth/token', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

        Route::middleware('offline.member')
            ->prefix('organisations/{organisation:slug}')
            ->name('organisations.')
            ->group(function (): void {
                Route::get('/farms', [FarmController::class, 'index'])->name('farms.index');

                Route::get('/flocks', [FlockController::class, 'index'])->name('flocks.index');
                Route::get('/flocks/{flock}', [FlockController::class, 'show'])->name('flocks.show');

                Route::get('/daily-records', [DailyRecordController::class, 'index'])->name('daily-records.index');
                Route::post('/sync', [DailyRecordController::class, 'sync'])->name('sync');

                Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
                Route::get('/products', [ProductController::class, 'index'])->name('products.index');

                Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
                Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
                Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
                Route::post('/sales/{sale}/payments', [SaleController::class, 'storePayment'])->name('sales.payments.store');

                Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');
                Route::post('/stock-movements', [StockController::class, 'storeMovement'])->name('stock-movements.store');
            });
    });
});
