<?php

namespace App\Http\Controllers\Member;

use App\Cart;
use App\CartProduct;
use App\CartProductOption;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ValidatesProductCartInput;
use App\Product;
use App\ProductOptionItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddToCartController extends Controller
{
    use ValidatesProductCartInput;

    public function __construct()
    {
        $this->middleware('web');
    }

    public function addToCart(Request $request, $id)
    {
        $product = Product::query()
            ->withStorefrontStock()
            ->storefrontAvailable()
            ->where('products.id', decrypt($id))
            ->firstOrFail();

        $user = Auth::guard('web')->user();

        $data = $this->validateAddToCart($request, $product);
        if (isset($data['error']) && $data['error']) {
            return back()->withInput()->withErrors($data['field_err']);
        }

        $product_price = Product::get_today_price($product->id, $user);
        $linePrice = $product->calculateLinePrice(
            $product_price,
            isset($data['quantity']) ? (float) $data['quantity'] : null,
            isset($data['weight']) ? (float) $data['weight'] : null
        );
        $product_options = Product::getOption($product->id, true)['product_option'];
        $cart = Cart::where('user_id', $user->id)->where('status', Cart::$status['pending'])->first();

        if (empty($cart)) {
            $cart = Cart::create(
                [
                    'user_id' => $user->id,
                    'status' => Cart::$status['pending'],
                ]
            );
        }

        // process cart selected product
        // user already added the product into cart, we check if there are same option
        $check_exist = DB::table('cart_products')
            ->select('cart_products.id', DB::raw('count(*) as count'))
            ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
            ->leftJoin('cart_product_options', 'cart_product_options.cart_product_id', '=', 'cart_products.id')
            ->where('carts.status', Cart::$status['pending'])
            ->where('cart_products.status', CartProduct::$status['active'])
            ->where('carts.user_id', $user->id)
            ->where(
                function ($query) use ($product, $data) {
                    $query->where('cart_products.product_id', $product->id);

                    // Check if product_option exists in data
                    if (isset($data['product_option']) && is_array($data['product_option'])) {
                        foreach ($data['product_option'] as $opt => $opt_val) {
                            $query->where(
                                function ($subQuery) use ($opt, $opt_val) {
                                    $subQuery->where('cart_product_options.option', $opt)
                                        ->where('cart_product_options.option_item', $opt_val);
                                }
                            );
                        }
                    } else {
                        // If no product_option, consider cart_product_options as null
                        $query->whereNull('cart_product_options.cart_product_id');
                    }
                }
            )
            ->groupBy('cart_products.id');
                    
        $option_updated = false;
        foreach ($check_exist->get() as $key => $value) {
            if (($value->count == count($product_options) && !$option_updated) || empty($product_options)) {
                $option_updated = true;
                $cart_products = CartProduct::find($value->id);
                $cart_products->update(
                    [
                        'quantity' => $data['quantity'] ?? null,
                        'weight' => $data['weight'] ?? null,
                        'unit_price' => $product_price,
                        'price' => $linePrice,
                        'remark' => $data['remark'],
                    ]
                );
            }
        }

        if (!$option_updated) {
            $cart_product = CartProduct::create(
                [
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $data['quantity'] ?? null,
                    'weight' => $data['weight'] ?? null,
                    'unit_price' => $product_price,
                    'price' => $linePrice,
                    'remark' => isset($data['remark']) ? $data['remark'] : null,
                    'status' => CartProduct::$status['active'],
                ]
            );

            foreach ($data['product_option']??[] as $opt => $opt_val) {
                CartProductOption::create(
                    [
                        'cart_product_id' => $cart_product->id,
                        'option' => $opt,
                        'option_item' => $opt_val,
                        'status' => CartProductOption::$status['active'],
                    ]
                );
            }
        }

        return redirect()->back()->with('success', ($data['quantity'] ?? $data['weight']) . " $product->name has been ". ($option_updated? 'updated' : 'added') ." to cart successfully.");
    }
}
