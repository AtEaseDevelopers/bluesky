<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Driver Web App Routes
|--------------------------------------------------------------------------
|
| Mobile-responsive web portal used by delivery drivers to view assigned
| delivery orders, update delivery status, and record customer payments.
|
*/

Route::namespace('Driver')->middleware(['web'])->prefix('driver')->group(
    function () {
        Route::get('/login', 'LoginController@showForm')->name('driver.login');
        Route::post('/login', 'LoginController@login')->name('driver.login.submit');
        Route::get('/logout', 'LoginController@logout')->name('driver.logout');

        Route::group(
            ['middleware' => ['auth_driver', 'driver_bootstrap', 'driver.permission'], 'as' => 'driver.'],
            function () {
                Auth::setDefaultDriver('web_driver');

                Route::get('/', function () {
                    return redirect(route('driver.orders.index'));
                });

                // Assigned delivery orders
                Route::get('/orders', 'DeliveryOrderController@index')->name('orders.index');
                Route::get('/orders/{id}', 'DeliveryOrderController@show')->name('orders.show');
                Route::post('/orders/{id}/status', 'DeliveryOrderController@updateStatus')->name('orders.update-status');
                Route::post('/orders/{id}/payment', 'DeliveryOrderController@recordPayment')->name('orders.record-payment');
                Route::get('/orders/{id}/payment-proof', 'DeliveryOrderController@downloadProof')->name('orders.payment-proof');
            }
        );
    }
);
