<?php

namespace App\Http\Controllers\Public;

use App\Cart;
use App\CartProduct;
use App\CartProductOption;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\OrderProductOption;
use App\Product;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * General Customer (public) ordering link.
 *
 * One shared link, no account required. Guests reuse the existing customer
 * portal screens (member.product / member.cart / member.checkout) via a
 * session-keyed cart, and place a COD-only order. No order history is kept
 * against any user account (orders.user_id stays null, is_general = true).
 */
class PublicOrderController extends Controller
{
    /** Storefront — renders the member product screen with guest pricing. */
    public function index(Request $request)
    {
        $products = Product::query()
            ->select('products.*', 'product_stocks.quantity as stock_quantity', 'uoms.uom_name')
            ->join('product_stocks', 'product_stocks.product_id', '=', 'products.id')
            ->leftJoin('uoms', 'uoms.id', '=', 'products.uom_id')
            ->where('products.status', Product::$status['active'])
            ->where('product_stocks.quantity', '>', 0)
            ->when($request->keyword, function ($q) use ($request) {
                $q->where('products.name', 'LIKE', '%' . $request->keyword . '%');
            })
            ->orderBy('products.nos')
            ->get()
            ->map(function ($product) {
                $product->original_price = $product->price = Product::getPublicTodayPrice($product->id);
                $product->image_url = Product::resolveImageUrl($product);
                $uomName = $product->uom_name ?? optional($product->uom)->uom_name;
                $product->stock_label = Product::formatStockQuantity((float) $product->stock_quantity, $uomName);
                $product->price_label = Product::formatUnitPrice((float) $product->price, $uomName);
                $product->original_price_label = Product::formatUnitPrice((float) $product->original_price, $uomName);
                $product->added_to_cart = null;
                return $product;
            });

        return view('member.product', [
            'user' => $this->guestUser(),
            'products' => $products,
            'preferred_products' => collect(),
            'keyword' => $request->keyword,
        ]);
    }

    /** Add a product to the guest's session cart (id is encrypted, like the member flow). */
    public function addToCart(Request $request, $id)
    {
        $product = Product::find($this->decryptId($id));
        if (!$product || $product->status != Product::$status['active']) {
            abort(404);
        }

        $data = $this->validateAddToCart($request, $product);
        if (isset($data['error']) && $data['error']) {
            return back()->withInput()->withErrors($data['field_err']);
        }

        $price = Product::getPublicTodayPrice($product->id);
        $cart = $this->currentCart($request, true);

        $cart_product = CartProduct::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $data['quantity'] ?? null,
            'weight' => $data['weight'] ?? null,
            'unit_price' => $price,
            'price' => $price * ($data['quantity'] ?? $data['weight']),
            'remark' => $data['remark'] ?? null,
            'status' => CartProduct::$status['active'],
        ]);

        foreach ($data['product_option'] ?? [] as $opt => $opt_val) {
            if ($opt_val) {
                CartProductOption::create([
                    'cart_product_id' => $cart_product->id,
                    'option' => $opt,
                    'option_item' => $opt_val,
                    'status' => CartProductOption::$status['active'],
                ]);
            }
        }

        return redirect()->route('public.guest.cart')->with('success', 'Item added to your order.');
    }

    /** Cart — renders the member cart screen. */
    public function cart(Request $request)
    {
        [$products, $total] = $this->cartProducts($request);

        return view('member.cart', [
            'user' => $this->guestUser(),
            'products' => $products,
            'total' => number_format($total, 2, '.', ''),
        ]);
    }

    /** Update a cart line quantity/weight (session-scoped). */
    public function updateCartItem(Request $request)
    {
        $cart_product = CartProduct::find($request->id);
        if ($cart_product && $this->ownsCartProduct($request, $cart_product)) {
            $price = Product::getPublicTodayPrice($cart_product->product_id);
            $cart_product->update([
                'quantity' => $request->quantity ?? null,
                'weight' => $request->weight ?? null,
                'unit_price' => $price,
                'price' => $price * ($request->quantity ?? $request->weight),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /** Remove a cart line (session-scoped). */
    public function removeCartItem(Request $request, CartProduct $cart_product)
    {
        if ($this->ownsCartProduct($request, $cart_product)) {
            $cart_product->update(['status' => CartProduct::$status['removed']]);
        }

        return redirect()->route('public.guest.cart')->with('success', 'Item removed from your order.');
    }

    /** Checkout form — renders the member checkout screen (COD only). */
    public function checkout(Request $request)
    {
        [$products, $total] = $this->cartProducts($request);

        if (!count($products)) {
            return redirect()->route('public.guest.index')->with('error', 'Your order is empty.');
        }

        return view('member.checkout', [
            'user' => $this->guestUser(),
            'customer' => $this->guestUser(),
            'products' => $products,
            'total' => number_format($total, 2, '.', ''),
        ]);
    }

    /** Place the order. */
    public function placeOrder(Request $request)
    {
        $data = $this->validateCheckout($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        [$products, $total] = $this->cartProducts($request);
        if (!count($products)) {
            return redirect()->route('public.guest.index')->with('error', 'Your order is empty.');
        }

        $cart = $this->currentCart($request);
        $cart->update(['status' => Cart::$status['completed']]);

        $address = $data['shipping_address'] ?? $data['billing_address'];

        $order = Order::create([
            'user_id' => null,
            'is_general' => true,
            'order_type' => Order::$order_types['public'],
            'cart_id' => $cart->id,
            'total_price' => $total,
            'subtotal' => $total,
            'attn_name' => $data['attn_name'],
            'attn_contact' => $data['attn_contact'],
            'billing_address' => $data['billing_address'],
            'shipping_address' => $address,
            'payment_method' => 'cod', // public orders are COD only
            'status' => Order::$status['pending'],
        ]);

        $order_weight = 0;
        foreach ($products as $value) {
            $product_weight = DB::table('products')->where('id', $value->product_id)->value('weight');

            $order_product = OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $value->product_id,
                'product_name' => $value->name,
                'quantity' => $value->quantity ?? null,
                'weight' => $value->weight ?? null,
                'product_weight' => $product_weight,
                'unit_price' => $value->unit_price,
                'price' => $value->price,
                'remark' => $value->remark,
                'status' => OrderProduct::$status['active'],
            ]);

            if ($value->quantity != null) {
                $order_weight += $value->quantity * $product_weight;
            } else {
                $order_weight += $value->weight;
            }

            foreach ($value->options as $opt => $opt_itm) {
                if ($opt_itm) {
                    OrderProductOption::create([
                        'order_product_id' => $order_product->id,
                        'option' => $opt,
                        'option_item' => $opt_itm,
                        'status' => OrderProductOption::$status['active'],
                    ]);
                }
            }
        }

        $order->update(['order_weight' => $order_weight]);

        return redirect()->route('public.guest.index')->with(
            'success',
            "Thank you! Your order #{$order->id} has been received. Please pay cash on delivery."
        );
    }

    /* -------------------- helpers -------------------- */

    /** A blank, non-persisted user so the shared member views can read prices. */
    private function guestUser()
    {
        $guest = new User();
        $guest->price_permission = true;

        return $guest;
    }

    private function decryptId($id)
    {
        try {
            return decrypt($id);
        } catch (\Exception $e) {
            return $id; // tolerate a plain id (e.g. tests / direct calls)
        }
    }

    private function currentCart(Request $request, $create = false)
    {
        $sessionId = $request->session()->getId();

        $cart = Cart::whereNull('user_id')
            ->where('session_id', $sessionId)
            ->where('status', Cart::$status['pending'])
            ->first();

        if (!$cart && $create) {
            $cart = Cart::create([
                'session_id' => $sessionId,
                'status' => Cart::$status['pending'],
            ]);
        }

        return $cart;
    }

    private function ownsCartProduct(Request $request, CartProduct $cart_product)
    {
        $cart = $this->currentCart($request);
        return $cart && $cart_product->cart_id == $cart->id;
    }

    /** Returns [collection of cart lines (priced), total]. */
    private function cartProducts(Request $request)
    {
        $cart = $this->currentCart($request);
        if (!$cart) {
            return [collect(), 0];
        }

        $products = DB::table('cart_products')
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
            ->leftJoin('products', 'products.id', '=', 'cart_products.product_id')
            ->where('cart_products.cart_id', $cart->id)
            ->where('cart_products.status', CartProduct::$status['active'])
            ->get();

        $total = 0;
        foreach ($products as $key => $value) {
            $images = $value->images ? json_decode($value->images, true) : null;
            $products[$key]->image_url = isset($images[0])
                ? url('/') . '/' . Product::$path . '/' . $value->product_id . '/' . $images[0]
                : asset('assets/images/product-default.jpg');
            $products[$key]->options = CartProduct::getOption($value->cart_product_id);
            $products[$key]->unit_price = Product::getPublicTodayPrice($value->product_id);
            $total += $products[$key]->unit_price * ($value->quantity ?? $value->weight);
        }

        return [$products, $total];
    }

    private function validateAddToCart(Request $request, Product $product)
    {
        $rules = [
            'quantity' => ['required_without:weight', 'numeric'],
            'weight' => ['required_without:quantity', 'numeric'],
            'remark' => ['nullable', 'max:200'],
        ];

        $product_option = Product::getOption($product->id, true);
        foreach ($product_option['product_option'] as $option => $option_items) {
            $rules['product_option.' . $option] = [
                $product_option['product_option_mandatory'][$option] ? 'required' : 'nullable',
                'in:' . implode(',', $option_items),
            ];
        }

        try {
            return $request->validate($rules);
        } catch (ValidationException $err) {
            return ['error' => true, 'field_err' => $err->validator->errors()->getMessages()];
        }
    }

    private function validateCheckout(Request $request)
    {
        $rules = [
            'attn_name' => ['required', 'string', 'max:30'],
            'attn_contact' => ['required', 'string', 'max:30'],
            'billing_address' => ['required', 'string', 'max:100'],
            'shipping_address' => ['nullable', 'string', 'max:100'],
        ];

        try {
            return $request->validate($rules);
        } catch (ValidationException $err) {
            return ['error' => true, 'field_err' => $err->validator->errors()->getMessages()];
        }
    }
}
