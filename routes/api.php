<?php

use App\Http\Controllers\Webhooks\MobileMoneyWebhookController;
use Illuminate\Support\Facades\Route;

// Webhooks opérateurs mobile money : corps signé HMAC-SHA256 (voir config/mobilemoney.php)
Route::post('/webhooks/mobile-money/{operator}', MobileMoneyWebhookController::class)
    ->where('operator', 'orange|mtn|moov|wave')
    ->name('webhooks.mobile-money');
