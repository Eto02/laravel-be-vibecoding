<?php

use App\Http\Controllers\Api\Cart\CartController;
use App\Http\Controllers\Api\Cart\WishlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Cart
    Route::prefix('cart')->name('cart.')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('index');
        Route::post('/items', [CartController::class, 'store'])->name('items.store');
        Route::put('/items/{cartItem}', [CartController::class, 'update'])->name('items.update');
        Route::delete('/items/{cartItem}', [CartController::class, 'destroy'])->name('items.destroy');
        Route::delete('/', [CartController::class, 'clear'])->name('clear');
    });

    // Wishlist
    Route::prefix('wishlist')->name('wishlist.')->group(function () {
        Route::get('/', [WishlistController::class, 'index'])->name('index');
        Route::post('/items', [WishlistController::class, 'store'])->name('items.store');
        Route::delete('/items/{productId}', [WishlistController::class, 'destroy'])->name('items.destroy');
        Route::get('/items/{productId}/check', [WishlistController::class, 'check'])->name('items.check');
    });
});
