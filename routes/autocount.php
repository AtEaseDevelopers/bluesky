<?php

use App\Http\Controllers\Api\AutoCountController;
use Illuminate\Support\Facades\Route;

/*
| AutoCount plugin routes — higher rate limit than default API (bulk import).
| Authenticated via X-AutoCount-Token in AutoCountController.
*/

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

Route::prefix('invoices')->group(function () {
    Route::post('/import', [AutoCountController::class, 'invoicesImport']);
});
