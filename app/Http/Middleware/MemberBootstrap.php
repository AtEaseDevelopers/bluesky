<?php

namespace App\Http\Middleware;

use App\Cart;
use App\CartProduct;
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

        return $next($request);
    }
}
