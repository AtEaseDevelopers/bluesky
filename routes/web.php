<?php

use Illuminate\Support\Facades\Route;

$adminUrl = config('app.admin_url');

// if ((request()->isSecure()? "https://" : "http://") . request()->getHost() == $adminUrl) {
//     // Requests with URLs matching the ADMIN_URL prefix
    require __DIR__.'/web_admin.php';
// } else {
    // Route::namespace('Member')->prefix(env('APP_URL'))->middleware(['bootstrap'])->group(function () {
    Route::namespace('Member')->middleware(['bootstrap'])->group(
        function () {
            Route::get('/', 'HomeController@index');
            Route::get('/fast-login/{login_code}', 'LoginController@fastLogin');
            Route::get('/login', 'LoginController@getForm')->name('login');
            Route::post('/login', 'LoginController@login')->name('login.submit');
            Route::get('/logout', 'LoginController@logout')->name('logout');

            Route::get('/order/public/{token}', 'PublicOrderController@products')->name('public.order');
            Route::post('/order/public/{token}/cart/add/{product}', 'PublicOrderController@addToCart')->name('public.order.cart.add');
            Route::get('/order/public/{token}/cart', 'PublicOrderController@cart')->name('public.order.cart');
            Route::post('/order/public/{token}/cart/remove/{product}', 'PublicOrderController@removeFromCart')->name('public.order.cart.remove');
            Route::get('/order/public/{token}/checkout', 'PublicOrderController@checkoutForm')->name('public.order.checkout');
            Route::post('/order/public/{token}/checkout', 'PublicOrderController@store')->name('public.order.store');

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
