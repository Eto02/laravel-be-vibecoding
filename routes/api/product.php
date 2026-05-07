<?php

use App\Http\Controllers\Api\Product\CategoryController;
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Product\ProductMediaController;
use App\Http\Controllers\Api\Product\ProductVariantController;

// ── Public ────────────────────────────────────────────────────────────────────

Route::prefix('categories')->name('product.categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::get('/{category:slug}', [CategoryController::class, 'show'])->name('show');
});

// ── Admin — Category Management ───────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/categories')->name('admin.categories.')->group(function () {
    Route::post('/', [CategoryController::class, 'store'])->name('store');
    Route::put('/{category:slug}', [CategoryController::class, 'update'])->name('update');
    Route::delete('/{category:slug}', [CategoryController::class, 'destroy'])->name('destroy');
});

Route::prefix('products')->name('product.products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/{product}', [ProductController::class, 'show'])->name('show');
    Route::get('/{product}/variants', [ProductController::class, 'variants'])->name('variants');
});

// ── Merchant ──────────────────────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'merchant'])->prefix('merchant/products')->name('merchant.products.')->group(function () {
    Route::get('/', [ProductController::class, 'merchantIndex'])->name('index');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::get('/{product}', [ProductController::class, 'merchantShow'])->name('show');
    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
    Route::put('/{product}/status', [ProductController::class, 'updateStatus'])->name('status');

    // Media
    Route::post('/{product}/media', [ProductMediaController::class, 'generateUrl'])->name('media.generate');
    Route::post('/{product}/media/confirm', [ProductMediaController::class, 'confirm'])->name('media.confirm');
    Route::delete('/{product}/media/{media}', [ProductMediaController::class, 'destroy'])->name('media.destroy');
    Route::put('/{product}/media/reorder', [ProductMediaController::class, 'reorder'])->name('media.reorder');

    // Variants
    Route::post('/{product}/variants', [ProductVariantController::class, 'store'])->name('variants.store');
    Route::put('/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('variants.update');
    Route::delete('/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('variants.destroy');
});
