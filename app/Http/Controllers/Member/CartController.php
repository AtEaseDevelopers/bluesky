<?php

namespace App\Http\Controllers\Member;

use App\Cart;
use App\CartProduct;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function index(Request $request)
    {
        $user = Auth::guard('web')->user();
        $cart_products = DB::table('cart_products')
            ->select(
                'cart_products.id as cart_product_id', 
                'products.id as product_id', 
                'products.images as images', 
                'products.name as name', 
                'products.sell_in',
                'cart_products.quantity', 
                'cart_products.weight',
                'cart_products.unit_price as original_unit_price', 
                'cart_products.price',
                'cart_products.remark'
            )
            ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
            ->leftJoin('products', 'products.id', '=', 'cart_products.product_id')
            ->where('cart_products.status', CartProduct::$status['active'])
            ->where('carts.user_id', $user->id)
            ->where('carts.status', Cart::$status['pending'])
            ->get();

        $total = 0;
        foreach ($cart_products as $key => $value) {
            if ($value->images != null) {
                $images = json_decode($value->images, true);
                $cart_products[$key]->image_url = url('/') . '/' . Product::$path."/".$value->product_id."/".$images[0];
            } else {
                $cart_products[$key]->image_url = '/assets/images/product-default.jpg';
            }
            $cart_products[$key]->options = CartProduct::getOption($value->cart_product_id);
            $cart_products[$key]->unit_price = Product::get_today_price($value->product_id, $user);
            $total += $cart_products[$key]->unit_price * $value->quantity;
        }

        return view(
            'member.cart', [
                'user' => $user,
                'products' => $cart_products,
                'total' => number_format($total, 2, '.', ''),
            ]
        );
    }
}
