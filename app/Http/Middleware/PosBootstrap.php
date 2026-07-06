<?php

namespace App\Http\Middleware;

use App\Services\PosService;
use Closure;
use Illuminate\Support\Facades\View;

class PosBootstrap
{
    public function handle($request, Closure $next)
    {
        $pos = app(PosService::class);

        View::share('isPos', true);
        View::share('posReady', $pos->isReady($request));
        View::share('posMode', $pos->mode($request));
        View::share('posCustomerLabel', $pos->customerLabel($request));
        View::share('cartCount', $pos->cartCount($request));
        View::share('customers', $pos->activeCustomers());
        View::share('portal', [
            'is_guest' => $pos->isGuest($request),
            'products_url' => route('admin.pos.index'),
            'cart_url' => route('admin.pos.cart'),
            'checkout_url' => route('admin.pos.checkout'),
            'orders_url' => null,
            'add_to_cart_name' => 'admin.pos.add-to-cart',
            'product_show_name' => null,
            'update_cart_url' => route('admin.pos.update-cart-item'),
            'remove_cart_url' => url('/admin/pos/remove-cart-item'),
            'product_info_url' => route('admin.pos.add-to-cart-product-info'),
        ]);

        return $next($request);
    }
}
