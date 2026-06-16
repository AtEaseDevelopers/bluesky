<?php

use App\Http\Controllers\Admin\OrderPdfController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\PdfHelper;
use App\Order;

// Route::namespace('Admin')->middleware(['admin_bootstrap'])->prefix(env('ADMIN_URL'))->group(function () {
Route::namespace('Admin')->middleware(['admin_bootstrap'])->prefix('admin')->group(
    function () {
        // Route::get('/regen', function() {
        //     $orders = Order::get();

        //     for ($i = 0; $i < count($orders); $i++) {
        //         PdfHelper::GenerateOrderInvoice($orders[$i]);
        //         PdfHelper::GenerateOrderInvoiceWithoutPrice($orders[$i]);
        //         PdfHelper::GenerateDeliveryOrder($orders[$i]);
        //     }
        // });

        Route::get('/login', 'LoginController@showForm')->name('admin.login');
        Route::post('/login', 'LoginController@login')->name('admin.login.submit');
        Route::get('/logout', 'LoginController@logout')->name('admin.logout');

        Route::group(
            ['middleware' => ['auth_admin', 'admin_role_check'], 'as' => 'admin.'],
            function () {
                Auth::setDefaultDriver('web_admin');

                // Route::get('/pdf', function() {
                //     $orders = Order::where('id', 68)->get();
                //     for ($i=0; $i < count($orders); $i++) {
                //         // generate invoice and DO
                //         PdfHelper::GenerateOrderInvoice($orders[$i]);
                //         PdfHelper::GenerateOrderInvoiceWithoutPrice($orders[$i]);
                //         PdfHelper::GenerateDeliveryOrder($orders[$i]);
                //     }

                //     dd('done');
                // });

                // Admin routes
                Route::get(
                    '/', function () {
                        return redirect('dashboard');
                    }
                );
                Route::get('/dashboard', 'DashboardController@index')->name('dashboard');
                Route::get('/profile', 'DashboardController@profile')->name('profile');
                Route::post('/profile', 'DashboardController@profile_update')->name('profile-update');

                // product
                Route::get('/products', 'ProductController@index')->name('products');
                Route::get('/products/export', 'ProductController@export');
                Route::get('/products-import', 'ProductController@import')->name('products-import.index');
                Route::post('/products-import', 'ProductController@import_store')->name('products-import.store');

                Route::get('/product/add', 'AddProductController@showForm')->name('products.create');
                Route::post('/product/add', 'AddProductController@addProduct')->name('products.store');

                Route::get('/product/edit/{product}', 'EditProductController@showForm')->name('products.edit');
                Route::post('/product/edit/{product}', 'EditProductController@editProduct')->name('products.update');
                Route::get('/product/remove/{product}', 'EditProductController@removeProduct');

                // Product Daily Price
                Route::get('/product-daily-prices', 'ProductDailyPriceController@index');
                Route::get('/product-daily-price/add', 'AddProductDailyPriceController@showForm');
                Route::get('/product-daily-price/add/{product_daily_price_id}', 'AddProductDailyPriceController@showForm');
                Route::get('/product-daily-price/add/{date}/{duplicate_to_date}', 'AddProductDailyPriceController@showForm');
                Route::post('/product-daily-price/add/{date}', 'AddProductDailyPriceController@addProductDailyPriceBatch');
                Route::post('/product-daily-price/add', 'AddProductDailyPriceController@addProductDailyPrice');
                // Route::get('/product-daily-price/edit/{product_daily_price}', 'EditProductDailyPriceController@showForm');
                // Route::post('/product-daily-price/edit/{product_daily_price}', 'EditProductDailyPriceController@editProductDailyPrice');
                Route::get('/product-daily-price/remove/{product_daily_price}', 'RemoveProductDailyPriceController@removeProductDailyPrice');

                Route::resource('areas', AreasController::class);
                Route::controller(AreasController::class)->group(
                    function () {
                        Route::post('/fetch-areas', 'fetch_areas');
                    }
                );

                Route::resource('uom', UomController::class);
                Route::controller(UomController::class)->group(
                    function () {
                        Route::post('/fetch-uom', 'fetch_uom');
                    }
                );

                Route::controller('InventoryController')->group(
                    function () {
                        Route::get('/inventory', 'index')->name('inventory.index');
                        Route::get('/inventory/stock-in', 'stockInCreate')->name('inventory.stock-in.create');
                        Route::post('/inventory/stock-in', 'stockInStore')->name('inventory.stock-in.store');
                        Route::get('/inventory/stock-out', 'stockOutCreate')->name('inventory.stock-out.create');
                        Route::post('/inventory/stock-out', 'stockOutStore')->name('inventory.stock-out.store');
                        Route::get('/inventory/movements', 'movements')->name('inventory.movements');
                        Route::post('/fetch-stock-balances', 'fetch_balances');
                        Route::post('/fetch-stock-movements', 'fetch_movements');
                    }
                );

                Route::resource('product-categories', ProductCategoriesController::class);
                Route::controller(ProductCategoriesController::class)->group(
                    function () {
                        Route::post('/fetch-product-categories', 'fetch_categories');
                    }
                );

                Route::resource('admins', AdminController::class);
                    Route::controller(AdminController::class)->group(
                        function () {
                            Route::post('/fetch-admins', 'fetch_admins');
                            Route::post('/update-admin-status', 'update_status');
                        }
                    );

                // customer
                Route::get('/customers', 'CustomerController@index')->name('customers');
                Route::get('/customers/export', 'CustomerController@export')->name('customers.export');
                Route::get('/import-customers', 'CustomerController@import_customers')->name('import.customers');
                Route::post('/import-customers', 'CustomerController@import_customers_submit')->name('import.customers.submit');
                Route::get('/customer/add', 'AddCustomerController@showForm')->name('customers.create');
                Route::post('/customer/add', 'AddCustomerController@addCustomer')->name('customers.store');
                Route::get('/customer/edit/{customer}', 'EditCustomerController@showForm')->name('customers.edit');
                Route::post('/customer/edit/{customer}', 'EditCustomerController@editCustomer')->name('customers.update');
                Route::post('/customer/update-password', 'EditCustomerController@updatePassword')->name('customer.update-password');
                Route::get('/customer/generate-new-login-link/{customer}', 'EditCustomerController@generateNewLoginLink')->name('customers.generate-new-login-link');
                Route::post('/delete-customer-visibility-product', 'CustomerController@deleteCustomerProduct');
                Route::post('/get-products-for-category', 'AddCustomerController@getProductsForCategory');

                Route::resource('lorry', 'LorryController');
                Route::post('/get-lorry', 'LorryController@get_lorry');

                Route::resource('customer-categories', CustomerCategoryController::class);
                    Route::controller(CustomerCategoryController::class)->group(
                        function () {
                            Route::post('/fetch-customer-categories', 'fetch_categories');
                        }
                    );
                // order
                Route::get('/orders', 'OrderController@index')->name('orders');
                Route::get('/orders/export', 'OrderController@export')->name('orders.export');
                Route::post('/change-order-status', 'OrderController@change_order_status')->name('change-order-status');
                Route::post('/change-order-lorry', 'OrderController@change_order_lorry')->name('change-order-lorry');
                Route::post('/assign-order-driver', 'OrderController@assign_order_driver')->name('assign-order-driver');
                Route::get('/order/add', 'AddOrderController@showForm')->name('orders.create');
                Route::post('/order/add', 'AddOrderController@addOrder')->name('orders.store');
                Route::post('/order/get-customer-info', 'AddOrderController@getCustomerData')->name('orders.get-customer-info');
                Route::get('/order/get-products/{customer}', 'AddOrderController@getProducts')->name('orders.get-products');
                Route::get('/order/summary/{order}', 'OrderController@viewSummary')->name('orders.summary');
                Route::get('/order/edit/{order}', 'EditOrderController@showForm')->name('orders.edit');
                Route::post('/order/edit/{order}', 'EditOrderController@editOrder')->name('orders.update');
                Route::get('/order/get-order-info/{order}', 'EditOrderController@getOrderData')->name('orders.get-order-info');
                Route::post('/order/update-status/{order}', 'UpdateOrderStatusController@index');
                Route::post('/order/batch-update-status', 'UpdateOrderStatusController@batchUpdate');
                Route::get('/order/batch-download-files', 'OrderController@downloadInvoiceDoAsZip');
                Route::post('/order-products-list', 'OrderController@order_products_list');
                Route::post('/update-order-products-weight', 'OrderController@update_order_products_weight')->name('update-order-products-weight');
                Route::get('/download_do_zip', 'OrderController@download_do_zip');

                // Order PDF Routes
                Route::get('/orders/{id}/invoice', [OrderPdfController::class, 'invoice'])->name('order.invoice');
                Route::get('/orders/{id}/invoice2', [OrderPdfController::class, 'invoiceWithoutPrice'])->name('order.invoice2');
                Route::get('/orders/{id}/delivery-order', [OrderPdfController::class, 'deliveryOrder'])->name('order.delivery-order');

                Route::get('/daily-sales-report', 'ReportsController@daily_sales_report')->name('daily-sales-report');
                Route::get('/export-daily-sales-report', 'ReportsController@export_daily_sales_report')->name('export-daily-sales-report');
                Route::get('/daily-summary-report', 'ReportsController@daily_summary_report')->name('daily-summary-report');
                Route::get('/export-daily-summary-report', 'ReportsController@export_daily_summary_report')->name('export-daily-summary-report');
                Route::get('/daily-summary-stock-report', 'ReportsController@daily_summary_stock_report')->name('daily-summary-stock-report');
                Route::get('/export-daily-summary-stock-report', 'ReportsController@export_daily_summary_stock_report')->name('export-daily-summary-stock-report');
                Route::get('/do-report', 'ReportsController@do_report')->name('do-report');
                Route::get('/sql-do-export-report', 'ReportsController@sql_do_export_report')->name('sql-do-export-report');
                Route::get('/sql-do-export-report-excel', 'ReportsController@sql_do_export_report_excel')->name('sql-do-export-report-excel');
            }
        );

    }
);
