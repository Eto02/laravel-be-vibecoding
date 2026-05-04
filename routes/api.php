<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\OAuthController;
use App\Http\Controllers\Api\Media\MediaController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Payment\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login',    [AuthController::class, 'login'])->name('login');
    Route::post('/refresh',  [AuthController::class, 'refresh'])->name('refresh');

    Route::get('/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me',      [AuthController::class, 'me'])->name('me');
    });
});

Route::middleware('auth:sanctum')->prefix('payments')->name('payment.')->group(function () {
    Route::post('/', [PaymentController::class, 'store'])->name('store');
});

Route::prefix('webhooks')->name('webhook.')->group(function () {
    Route::post('/xendit', [WebhookController::class, 'xendit'])->name('xendit');
});

Route::middleware('auth:sanctum')->prefix('media')->name('media.')->group(function () {
    Route::post('/presigned-url', [MediaController::class, 'presignedUrl'])->name('presigned-url');
    Route::post('/confirm',       [MediaController::class, 'confirm'])->name('confirm');
    Route::delete('/',            [MediaController::class, 'delete'])->name('delete');
});
