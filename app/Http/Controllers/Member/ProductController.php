<?php

namespace App\Http\Controllers\Member;

use App\Cart;
use App\CartProduct;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use App\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function index(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect(route('login'))->with('error', 'Please login to continue using your account');
        }

        $id = $user->id;

        $keyword = $request->keyword;
    
        // Fetch all product IDs for the user
        $productIds = DB::table('product_visibilities')
            ->where('user_id', $user->id)
            ->pluck('product_id')
            ->toArray();
    
        // Fetch all active products with the necessary details in a single query
        $products = Product::where('status', Product::$status['active'])
            ->when($keyword, function ($q) use ($keyword) {
                return $q->where('products.name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('products.sku', 'LIKE', $keyword . '%')
                    ->orWhere('products.price', 'LIKE', $keyword . '%')
                    ->orWhere('products.weight', 'LIKE', $keyword . '%')
                    ->orWhere('products.status', 'LIKE', $keyword . '%');
            })
            ->whereIn('id', $productIds)
            ->get()
            ->map(
                function ($product) use ($user) {
                    $image = json_decode($product->images, true);
                    $product->original_price = $product->price;
                    $product->price = Product::get_today_price($product->id, $user);
                    if (isset($image[0])) {
                        $product->image_url = url('/') . '/' . Product::$path . "/" . $product->id . "/" . $image[0];
                    } else {
                        $product->image_url = asset('assets/images/product-default.jpg');
                    }
                    return $product;
                }
            );
    
        $products_output = [];
        foreach ($products as $key => $value) {
            $image = json_decode($value->images, true);
            $products[$key]->original_price = $value->price;
            $products[$key]->price = Product::get_today_price($value->id, $user);
            if (isset($image[0])) {
                $products[$key]->image_url = url('/') . '/' . Product::$path."/".$value->id."/".$image[0];
            } else {
                $products[$key]->image_url = asset('assets/images/product-default.jpg');
            }
                
            // check if in cart
            $products[$key]->added_to_cart = DB::table('cart_products')
                ->select('cart_products.quantity', 'cart_products.weight')
                ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
                ->leftJoin('products', 'products.id', '=', 'cart_products.product_id')
                ->where('cart_products.status', CartProduct::$status['active'])
                ->where('cart_products.product_id', $value->id)
                ->where('carts.user_id', $user->id)
                ->where('carts.status', Cart::$status['pending'])
                ->first();
    
            $products_output[$value->id] = $products[$key];
        }
    
            // get the preferred product
        $preferred_products = DB::table('order_products')
            ->select(DB::raw('products.id as id, products.name as name, SUM(order_products.quantity) as count'))
            ->leftJoin('orders', 'orders.id', '=', 'order_products.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            ->where('orders.status', '!=', Order::$status['cancelled'])
            ->where('products.status', Product::$status['active'])
            ->where('orders.user_id', $user->id)
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('count')
            ->limit(4)
            ->get();
            
        $preferred_products_output = [];

        foreach ($preferred_products as $key => $value) {
            if (isset($products_output[$value->id])) {
                if (isset($products[$key])) {
                    $products_output[$value->id]->sold_count = $value->count;
            
                    // check if in cart
                    $products_output[$value->id]->added_to_cart = DB::table('cart_products')
                            ->select('cart_products.quantity', 'cart_products.weight')
                            ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
                            ->leftJoin('products', 'products.id', '=', 'cart_products.product_id')
                            ->where('cart_products.status', CartProduct::$status['active'])
                            ->where('cart_products.product_id', $value->id)
                            ->where('carts.user_id', $user->id)
                            ->where('carts.status', Cart::$status['pending'])
                            ->first();
            
                    // $products_output[$value->id] = $products[$key];
                    $preferred_products_output[] = $products_output[$value->id];
                }
            }
        }

        // dd($id);
    
        // Return the view with data
        return view(
            'member.product', [
                'user' => $user,
                'keyword' => $keyword,
                'products' => $products_output,
                'preferred_products' => $preferred_products_output,
            ]
        );
    }

    public function view($id)
    {
        $product = Product::find(decrypt($id));
        if ($product->status != Product::$status['active']) {
            abort(404);
        }

        $user = Auth::guard('web')->user();

        // user payment method
        $payment_method = json_decode($user->payment_method, true);

        $image = json_decode($product->images, true);
        $product->original_price = $product->price;
        $product->price = Product::get_today_price($product->id, $user);
        if (isset($image[0])) {
            $product->image_url = url('/') . '/' . Product::$path."/".$product->id."/".$image[0];
        } else {
            $product->image_url = asset('assets/images/product-default.jpg');
        }
        
        $product->product_option = Product::getOption($product->id, true);

        $product->cart_product_option = DB::table('cart_product_options')
            ->leftJoin('cart_products', 'cart_products.id', '=', 'cart_product_options.cart_product_id')
            ->select('option_item')
            ->where('cart_products.product_id', $product->id)
            ->first();

        // check if in cart
        $product->added_to_cart = DB::table('cart_products')
            ->select('cart_products.quantity', 'cart_products.weight')
            ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
            ->leftJoin('products', 'products.id', '=', 'cart_products.product_id')
            ->where('cart_products.status', CartProduct::$status['active'])
            ->where('cart_products.product_id', $product->id)
            ->where('carts.user_id', $user->id)
            ->where('carts.status', Cart::$status['pending'])
            ->first();

        return view(
            'member.view-product', [
                'payment_method' => $payment_method,
                'product' => $product,
            ]
        );
    }

    public function add_to_cart_product_info(Request $request)
    {
        $data['product'] = Product::where('id', decrypt($request['id']))->first();
        $data['product_option'] = Product::getOption(decrypt($request['id']), true);
        $data['cart_product_option'] = DB::table('cart_product_options')
            ->leftJoin('cart_products', 'cart_products.id', '=', 'cart_product_options.cart_product_id')
            ->select('option_item')
            ->where('cart_products.product_id', decrypt($request['id']))
            ->first();

        $view = view('member.includes.product_info', $data)->render();
        return Response::json(
            [
                'view' => $view
            ]
        );
    }
}
