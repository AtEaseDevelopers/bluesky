<?php

namespace App\Http\Middleware;

use App\Cart;
use App\CartProduct;
use App\OrderFieldSetting;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class MemberBootstrap
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(Auth::guard('web')->check()) {
            $cartCount = DB::table('carts')
                ->select('carts.id', DB::raw('count(*) as count'))
                ->leftJoin('cart_products', 'cart_products.cart_id', '=', 'carts.id')
                ->where('carts.user_id', Auth::guard('web')->user()->id)
                ->where('carts.status', Cart::$status['pending'])
                ->where('cart_products.status', CartProduct::$status['active'])
                ->groupBy('carts.id');
            View::share('cartCount', $cartCount->first()->count ?? 0);
        }

        // Route map mirrored by the public (guest) portal; member targets here.
        View::share('isGuest', false);
        View::share('portal', [
            'is_guest' => false,
            'products_url' => route('member.products'),
            'cart_url' => route('member.cart'),
            'checkout_url' => route('member.checkout'),
            'orders_url' => route('member.orders'),
            'bulk_payments_url' => route('member.bulk-payments'),
            'add_to_cart_name' => 'member.add-to-cart',
            'product_show_name' => 'member.products.show',
            'update_cart_url' => url('/update-cart-item'),
            'remove_cart_url' => url('/remove-cart-item'),
            'product_info_url' => url('/add-to-cart-product-info'),
        ]);

        View::share('orderFieldSettings', [
            'weight_presets' => OrderFieldSetting::weightPresets(),
            'situation_options' => OrderFieldSetting::situationOptions(),
            'situation_label' => OrderFieldSetting::situationLabel(),
        ]);

        return $next($request);
    }
}
