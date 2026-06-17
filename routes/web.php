<?php

use Illuminate\Support\Facades\Route;

$adminUrl = config('app.admin_url');

// if ((request()->isSecure()? "https://" : "http://") . request()->getHost() == $adminUrl) {
//     // Requests with URLs matching the ADMIN_URL prefix
    require __DIR__.'/web_admin.php';

    // Driver Web App (registered before the Member catch-all file route below)
    require __DIR__.'/web_driver.php';

    // General Customer (public) ordering link - no account required, COD only.
    // Registered before the Member catch-all file route below.
    Route::namespace('Public')->prefix('order')->middleware(['web', 'public_bootstrap'])->name('public.guest.')->group(
        function () {
            Route::get('/', 'PublicOrderController@index')->name('index');
            Route::post('/add-to-cart/{id}', 'PublicOrderController@addToCart')->name('add-to-cart');
            Route::post('/update-cart-item', 'PublicOrderController@updateCartItem')->name('update-cart-item');
            Route::get('/remove-cart-item/{cart_product}', 'PublicOrderController@removeCartItem')->name('remove-cart-item');
            Route::get('/cart', 'PublicOrderController@cart')->name('cart');
            Route::get('/checkout', 'PublicOrderController@checkout')->name('checkout');
            Route::post('/checkout', 'PublicOrderController@placeOrder')->name('checkout.submit');
        }
    );
// } else {
    // Route::namespace('Member')->prefix(env('APP_URL'))->middleware(['bootstrap'])->group(function () {
    Route::namespace('Member')->middleware(['bootstrap'])->group(
        function () {
            Route::get('/', 'HomeController@index');
            Route::get('/fast-login/{login_code}', 'LoginController@fastLogin');
            Route::get('/register/{token}', 'RegisterController@showForm')->name('register');
            Route::post('/register/{token}', 'RegisterController@register')->name('register.submit');
            Route::get('/login', 'LoginController@getForm')->name('login');
            Route::post('/login', 'LoginController@login')->name('login.submit');
            Route::get('/logout', 'LoginController@logout')->name('logout');

            Route::group(
                ['middleware' => ['web'], 'as' => 'member.'], function () {
                    Route::get('/products', 'ProductController@index')->name('products');
                    Route::get('/product/{product}', 'ProductController@view')->name('products.show');
                    Route::post('/add-to-cart/{product}', 'AddToCartController@addToCart')->name('add-to-cart');
                    Route::post('/update-cart-item', 'EditCartItemController@update');
                    Route::get('/remove-cart-item/{cart_product}', 'EditCartItemController@remove');
                    Route::post('/add-to-cart-product-info', 'ProductController@add_to_cart_product_info');

                    Route::get('/cart', 'CartController@index')->name('cart');

                    Route::get('/profile', 'ProfileController@index')->name('profile');
                    Route::post('/customer/update-password/', 'ProfileController@updatePassword')->name('update.password');

                    // checkout
                    Route::get('/checkout', 'CheckoutController@viewForm')->name('checkout');
                    Route::get('/checkout/{buy_again}', 'CheckoutController@viewForm');
                    Route::post('/checkout', 'CheckoutController@checkout');
                    Route::post('/checkout/{buy_again}', 'CheckoutController@checkout');

                    // order
                    Route::get('/orders', 'OrderController@index')->name('orders');
                    Route::get('/orders/export', 'OrderController@export');
                    Route::get('/order/summary/{order}', 'OrderController@viewSummary')->name('orders.summary');
                    Route::get('/order/review/{order}', 'OrderReviewController@show')->name('orders.review');
                    Route::post('/order/review/{order}/approve', 'OrderReviewController@approve')->name('orders.review.approve');
                    Route::post('/order/review/{order}/reject', 'OrderReviewController@reject')->name('orders.review.reject');
                    Route::post('/order/{order}/payments', 'OrderPaymentController@store')->name('orders.payments.store');
                    Route::get('/order/{order}/payments/{payment}/proof', 'OrderPaymentController@viewProof')->name('orders.payment-proof');
                    Route::get('/bulk-payments', 'BulkPaymentController@index')->name('bulk-payments');
                    Route::post('/bulk-payments', 'BulkPaymentController@store')->name('bulk-payments.store');

                    // Buy again
                    Route::get('/order/buy-again/{order}', 'BuyAgainController@index');
                }
            );

            // File Controller
            Route::get('{folder}/{id}/{filename}', 'FileController@download');
            Route::get('download/{folder}/{id}/{filename}', 'FileController@downloadAndUpdateStatus');
        }
    );
    // }

// Partials
Route::POST('/get-products-list', 'IndexController@get_products_list');

Route::get(
    '/clear-cache', function () {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        return redirect('/login');
    }
);
