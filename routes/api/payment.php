<?php

use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Payment\WalletController;
use App\Http\Controllers\Api\Payment\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('payments')->name('payment.payments.')->group(function () {
    Route::post('/initiate',     [PaymentController::class, 'initiate'])->name('initiate');
    Route::get('/{id}/status',   [PaymentController::class, 'status'])->name('status');
    Route::post('/{id}/switch',  [PaymentController::class, 'switch'])->name('switch');
    Route::post('/{id}/refund',  [PaymentController::class, 'refund'])->name('refund');
});

Route::middleware('auth:sanctum')->prefix('wallet')->name('payment.wallet.')->group(function () {
    Route::get('/balance',      [WalletController::class, 'balance'])->name('balance');
    Route::get('/transactions', [WalletController::class, 'transactions'])->name('transactions');
    Route::post('/topup',       [WalletController::class, 'topup'])->name('topup');
    Route::post('/withdraw',    [WalletController::class, 'withdraw'])->name('withdraw')
        ->middleware('merchant');
});

Route::prefix('webhooks')->name('payment.webhooks.')->group(function () {
    Route::post('/{provider}',  [WebhookController::class, 'handle'])->name('handle');
});
