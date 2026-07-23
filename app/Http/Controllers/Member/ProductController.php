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

        $products = Product::query()
            ->withStorefrontStock()
            ->memberCatalog($user)
            ->when($keyword, function ($q) use ($keyword) {
                return $q->where(function ($query) use ($keyword) {
                    $query->where('products.name', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('products.sku', 'LIKE', $keyword . '%');
                });
            })
            ->orderBy('products.name')
            ->get()
            ->map(function ($product) use ($user) {
                return $this->formatProductForMember($product, $user);
            });
    
        $products_output = [];
        foreach ($products as $product) {
            $product->added_to_cart = DB::table('cart_products')
                ->select('cart_products.quantity', 'cart_products.weight')
                ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
                ->where('cart_products.status', CartProduct::$status['active'])
                ->where('cart_products.product_id', $product->id)
                ->where('carts.user_id', $user->id)
                ->where('carts.status', Cart::$status['pending'])
                ->first();

            $products_output[$product->id] = $product;
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
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect(route('login'))->with('error', 'Please login to continue using your account');
        }

        $product = Product::query()
            ->withStorefrontStock()
            ->memberCatalog($user)
            ->where('products.id', decrypt($id))
            ->firstOrFail();

        $product = $this->formatProductForMember($product, $user);
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
                'payment_method' => json_decode($user->payment_method, true),
                'product' => $product,
            ]
        );
    }

    private function formatProductForMember(Product $product, $user): Product
    {
        $product->original_price = $product->price;
        $product->price = Product::get_today_price($product->id, $user);
        $product->image_url = Product::resolveImageUrl($product);
        $uomName = $product->uom_name ?? optional($product->uom)->uom_name;
        $product->storefront_available_amount = $product->storefrontAvailableAmount();
        $product->stock_label = Product::formatStorefrontStockLabel(
            $product,
            (float) $product->stock_quantity,
            (float) ($product->stock_weight ?? 0),
            $uomName
        );
        $product->price_label = Product::formatUnitPrice((float) $product->price, $uomName);
        $product->original_price_label = Product::formatUnitPrice((float) $product->original_price, $uomName);

        return $product;
    }

    public function add_to_cart_product_info(Request $request)
    {
        $user = Auth::guard('web')->user();
        $product = Product::query()
            ->withStorefrontStock()
            ->memberCatalog($user)
            ->where('products.id', decrypt($request['id']))
            ->firstOrFail();

        $data['product'] = $this->formatProductForMember($product, $user);
        $data['product_option'] = Product::getOption(decrypt($request['id']), true);
        $data['cart_product_options'] = DB::table('cart_product_options')
            ->leftJoin('cart_products', 'cart_products.id', '=', 'cart_product_options.cart_product_id')
            ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
            ->where('cart_products.product_id', decrypt($request['id']))
            ->where('carts.user_id', Auth::guard('web')->id())
            ->where('cart_products.status', CartProduct::$status['active'])
            ->pluck('option_item', 'option');

        $view = view('member.includes.product_info', $data)->render();
        return Response::json(
            [
                'view' => $view
            ]
        );
    }
}
