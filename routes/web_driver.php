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

                // Vehicle selection (driver chooses which lorry they are operating)
                Route::get('/vehicle', 'VehicleController@edit')->name('vehicle.edit');
                Route::post('/vehicle', 'VehicleController@update')->middleware('driver.lorry_available')->name('vehicle.update');

                // Assigned customers (invoice payment status & due dates)
                Route::get('/customers', 'CustomerController@index')->name('customers.index');
                Route::get('/customers/{id}', 'CustomerController@show')->name('customers.show');
                Route::post('/customers/{customer}/invoices/{order}/payment', 'CustomerController@recordPayment')->name('customers.record-payment');

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
