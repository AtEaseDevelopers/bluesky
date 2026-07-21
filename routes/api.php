<?php

use App\Http\Controllers\Api\AutoCountController;
use App\Http\Controllers\Api\DriverAuthController;
use App\Http\Controllers\Api\DriverOrderController;
use App\Http\Controllers\RevenueMonsterWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/revenue-monster', [RevenueMonsterWebhookController::class, 'notify'])
    ->name('webhooks.revenue-monster');

Route::prefix('order')->group(function () {
    Route::get('/pending', [AutoCountController::class, 'orderPending']);
    Route::get('/process', [AutoCountController::class, 'orderProcess']);
    Route::get('/edit', [AutoCountController::class, 'orderEdit']);
    Route::get('/paid', [AutoCountController::class, 'orderPaid']);
    Route::post('/update', [AutoCountController::class, 'orderUpdate']);
    Route::post('/update-paid', [AutoCountController::class, 'orderUpdatePaid']);
    Route::post('/update-log', [AutoCountController::class, 'orderUpdateLog']);
});

Route::prefix('customers')->group(function () {
    Route::get('/', [AutoCountController::class, 'customers']);
    Route::post('/update', [AutoCountController::class, 'customersUpdate']);
    Route::post('/import', [AutoCountController::class, 'customersImport']);
});

Route::prefix('products')->group(function () {
    Route::post('/import', [AutoCountController::class, 'productsImport']);
});

Route::prefix('driver')->group(function () {
    Route::post('/login', [DriverAuthController::class, 'login']);

    Route::middleware('auth_driver_api')->group(function () {
        Route::post('/logout', [DriverAuthController::class, 'logout']);
        Route::get('/me', [DriverAuthController::class, 'me']);
        Route::get('/orders', [DriverOrderController::class, 'index']);
        Route::get('/orders/{id}', [DriverOrderController::class, 'show']);
        Route::post('/orders/{id}/collect-cod', [DriverOrderController::class, 'collectCod']);
    });
});
