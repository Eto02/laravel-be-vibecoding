<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\OAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    // Public
    Route::post('/register',        [AuthController::class, 'register'])->name('register');
    Route::post('/login',           [AuthController::class, 'login'])->name('login');
    Route::post('/refresh',         [AuthController::class, 'refresh'])->name('refresh');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.forgot');
    Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->name('password.reset');

    // Email verification — signed URL (no auth middleware, signature is the guard)
    Route::get('/email/verify', [AuthController::class, 'verifyEmail'])->name('email.verify');

    // OAuth
    Route::get('/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout',           [AuthController::class, 'logout'])->name('logout');
        Route::get('/me',                [AuthController::class, 'me'])->name('me');
        Route::post('/email/resend',     [AuthController::class, 'resendVerification'])->name('email.resend');
        Route::put('/change-password',   [AuthController::class, 'changePassword'])->name('password.change');
        Route::get('/sessions',          [AuthController::class, 'sessions'])->name('sessions.index');
        Route::delete('/sessions/{id}',  [AuthController::class, 'revokeSession'])->name('sessions.destroy');
    });
});
