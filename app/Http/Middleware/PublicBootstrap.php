<?php

namespace App\Http\Middleware;

use App\Cart;
use App\CartProduct;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class PublicBootstrap
{
    /**
     * Share the guest (session-keyed) cart count with public ordering views.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $cartCount = DB::table('carts')
            ->leftJoin('cart_products', 'cart_products.cart_id', '=', 'carts.id')
            ->where('carts.session_id', $request->session()->getId())
            ->where('carts.status', Cart::$status['pending'])
            ->where('cart_products.status', CartProduct::$status['active'])
            ->count();

        View::share('cartCount', $cartCount);

        // Route map so the shared customer-portal views can target public endpoints.
        View::share('isGuest', true);
        View::share('portal', [
            'is_guest' => true,
            'products_url' => route('public.guest.index'),
            'cart_url' => route('public.guest.cart'),
            'checkout_url' => route('public.guest.checkout'),
            'orders_url' => null,
            'add_to_cart_name' => 'public.guest.add-to-cart',
            'product_show_name' => null,
            'update_cart_url' => route('public.guest.update-cart-item'),
            'remove_cart_url' => url('/order/remove-cart-item'),
            'product_info_url' => url('/add-to-cart-product-info'),
        ]);

        return $next($request);
    }
}
