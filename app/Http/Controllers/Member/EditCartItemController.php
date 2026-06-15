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

class EditCartItemController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('web');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function update(Request $request)
    {
        $cart_product = CartProduct::find($request['id']);
        $user = Auth::guard('web')->user();

        $product = Product::find($cart_product->product_id);
        $product_price = Product::get_today_price($product->id, $user);
        $cart_product->update(
            [
                'quantity' => $request->quantity ?? null,
                'weight' => $request->weight ?? null,
                'unit_price' => $product_price,
                'price' => $product_price * ($request->quantity ?? $request->weight),
            ]
        );

        return json_encode(
            [
                'success' => true,
            ]
        );
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function remove(Request $request, CartProduct $cart_product)
    {
        $cart_product->update(
            [
            'status' => CartProduct::$status['removed']
            ]
        );

        return redirect()->back()->with('success', "Item has been removed from cart successfully.");
    }
}
