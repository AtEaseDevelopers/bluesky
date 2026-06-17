<?php

use App\Http\Controllers\Api\DriverAuthController;
use App\Http\Controllers\Api\DriverOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('driver')->group(function () {
    Route::post('/login', [DriverAuthController::class, 'login']);

    Route::middleware('auth_driver')->group(function () {
        Route::post('/logout', [DriverAuthController::class, 'logout']);
        Route::get('/me', [DriverAuthController::class, 'me']);
        Route::get('/orders', [DriverOrderController::class, 'index']);
        Route::get('/orders/{id}', [DriverOrderController::class, 'show']);
        Route::post('/orders/{id}/collect-cod', [DriverOrderController::class, 'collectCod']);
    });
});
