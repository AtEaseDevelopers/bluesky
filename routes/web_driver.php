<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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

                Route::get('/orders', 'DeliveryOrderController@index')->name('orders.index');
                Route::get('/orders/{id}', 'DeliveryOrderController@show')->name('orders.show');
                Route::post('/orders/{id}/status', 'DeliveryOrderController@updateStatus')->name('orders.update-status');
                Route::post('/orders/{id}/adjust', 'DeliveryOrderController@adjustOrder')->name('orders.adjust');
                Route::post('/orders/{id}/payment', 'DeliveryOrderController@recordPayment')->name('orders.record-payment');
                Route::post('/orders/{id}/pay', 'RevenueMonsterPaymentController@generate')->name('orders.rm-pay');
                Route::get('/orders/{id}/pay/qr', 'RevenueMonsterPaymentController@show')->name('orders.rm-qr');
                Route::get('/orders/{id}/pay/status', 'RevenueMonsterPaymentController@status')->name('orders.rm-status');
                Route::get('/orders/{id}/payment-proof', 'DeliveryOrderController@downloadProof')->name('orders.payment-proof');
                Route::get('/orders/{id}/delivery-proof', 'DeliveryOrderController@downloadDeliveryProof')->name('orders.delivery-proof');

                Route::get('/profile', 'ProfileController@index')->name('profile');
                Route::post('/profile/password', 'ProfileController@updatePassword')->name('profile.update-password');
            }
        );
    }
);
