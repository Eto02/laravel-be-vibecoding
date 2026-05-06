<?php

use App\Http\Controllers\Api\Merchant\MerchantController;
use App\Http\Controllers\Api\Merchant\StoreController;
use App\Http\Controllers\Api\Store\PublicStoreController;
use App\Http\Controllers\Api\Store\StoreFollowerController;
use Illuminate\Support\Facades\Route;

// ── Merchant-owned routes ────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('merchant')->name('merchant.')->group(function () {
    Route::post('/register', [MerchantController::class, 'register'])->name('register');

    Route::middleware('merchant')->group(function () {
        Route::get('/store',              [MerchantController::class, 'show'])->name('store.show');
        Route::put('/store',              [StoreController::class, 'update'])->name('store.update');
        Route::get('/dashboard',          [MerchantController::class, 'dashboard'])->name('dashboard');
        Route::post('/kyc',               [MerchantController::class, 'uploadKyc'])->name('kyc.upload');
        Route::post('/kyc/confirm',       [MerchantController::class, 'confirmKyc'])->name('kyc.confirm');
    });
});

// ── Public store routes ───────────────────────────────────────────────────────
Route::prefix('stores')->name('stores.')->group(function () {
    Route::get('/{slug}',          [PublicStoreController::class, 'show'])->name('show');
    Route::get('/{slug}/products', [PublicStoreController::class, 'products'])->name('products');
    Route::get('/{slug}/followers', [StoreFollowerController::class, 'followers'])->name('followers');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/{slug}/follow',    [StoreFollowerController::class, 'follow'])->name('follow');
        Route::delete('/{slug}/follow',  [StoreFollowerController::class, 'unfollow'])->name('unfollow');
    });
});
