<?php

use App\Http\Controllers\Api\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('media')->name('media.')->group(function () {
    Route::post('/presigned-url', [MediaController::class, 'presignedUrl'])->name('presigned-url');
    Route::post('/confirm',       [MediaController::class, 'confirm'])->name('confirm');
    Route::delete('/',            [MediaController::class, 'delete'])->name('delete');
});
