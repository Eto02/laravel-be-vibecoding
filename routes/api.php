<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Media\MediaController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/payments', [PaymentController::class, 'store']);
});

Route::prefix('webhooks')->group(function () {
    Route::post('/xendit', [WebhookController::class, 'xendit']);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // OAuth Routes
    Route::get('/{provider}/redirect', [OAuthController::class, 'redirect']);
    Route::get('/{provider}/callback', [OAuthController::class, 'callback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->prefix('media')->name('media.')->group(function () {
    Route::post('/presigned-url', [MediaController::class, 'presignedUrl'])->name('presigned-url');
    Route::post('/confirm', [MediaController::class, 'confirm'])->name('confirm');
    Route::delete('/', [MediaController::class, 'delete'])->name('delete');
});
