<?php

use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Payment\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('payments')->name('payment.')->group(function () {
    Route::post('/', [PaymentController::class, 'store'])->name('store');
});

Route::prefix('webhooks')->name('webhook.')->group(function () {
    Route::post('/xendit', [WebhookController::class, 'xendit'])->name('xendit');
});
