<?php

use App\Http\Controllers\Api\Order\MerchantOrderController;
use App\Http\Controllers\Api\Order\OrderController;
use Illuminate\Support\Facades\Route;

// ── Buyer order routes ────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('orders')->name('order.orders.')->group(function () {
    Route::post('/checkout',            [OrderController::class, 'checkout'])->name('checkout');
    Route::get('/',                     [OrderController::class, 'index'])->name('index');
    Route::get('/{id}',                 [OrderController::class, 'show'])->name('show');
    Route::post('/{id}/cancel',         [OrderController::class, 'cancel'])->name('cancel');
    Route::post('/{id}/receive',        [OrderController::class, 'receive'])->name('receive');
    Route::post('/{id}/disputes',       [OrderController::class, 'dispute'])->name('dispute');
});

// ── Merchant order routes ─────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'merchant'])->prefix('merchant/orders')->name('order.merchant.')->group(function () {
    Route::get('/',                     [MerchantOrderController::class, 'index'])->name('index');
    Route::get('/{id}',                 [MerchantOrderController::class, 'show'])->name('show');
    Route::put('/{id}/confirm',         [MerchantOrderController::class, 'confirm'])->name('confirm');
    Route::put('/{id}/ship',            [MerchantOrderController::class, 'ship'])->name('ship');
});
