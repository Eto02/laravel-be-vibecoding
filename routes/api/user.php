<?php

use App\Http\Controllers\Api\User\AddressController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('users')->name('user.')->group(function () {
    // Profile
    Route::get('/me',            [UserController::class, 'show'])->name('profile.show');
    Route::put('/me',            [UserController::class, 'update'])->name('profile.update');

    // Avatar — two-step presigned upload
    Route::post('/me/avatar',         [UserController::class, 'uploadAvatar'])->name('avatar.upload');
    Route::post('/me/avatar/confirm', [UserController::class, 'confirmAvatar'])->name('avatar.confirm');

    // Phone verification
    Route::post('/phone/send-otp', [UserController::class, 'sendPhoneOtp'])->name('phone.send-otp');
    Route::post('/phone/verify',   [UserController::class, 'verifyPhone'])->name('phone.verify');

    // Address Book
    Route::prefix('me/addresses')->name('addresses.')->group(function () {
        Route::get('/',                [AddressController::class, 'index'])->name('index');
        Route::post('/',               [AddressController::class, 'store'])->name('store');
        Route::get('/{address}',       [AddressController::class, 'show'])->name('show');
        Route::put('/{address}',       [AddressController::class, 'update'])->name('update');
        Route::delete('/{address}',    [AddressController::class, 'destroy'])->name('destroy');
        Route::post('/{address}/set-default', [AddressController::class, 'setDefault'])->name('set-default');
    });
});
