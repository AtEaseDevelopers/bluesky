<?php

namespace App\Services;

use App\Cart;
use App\CartProduct;
use App\Product;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosService
{
    public const SESSION_MODE = 'pos.mode';
    public const SESSION_CUSTOMER_ID = 'pos.customer_id';
    public const SESSION_ADMIN_ID = 'pos.admin_id';
    public const SESSION_LAST_ORDER_ID = 'pos.last_order_id';

    public function sessionKey(Request $request): string
    {
        return 'pos:' . $request->session()->getId();
    }

    public function isReady(Request $request): bool
    {
        $mode = $request->session()->get(self::SESSION_MODE);

        if ($mode === 'guest') {
            return true;
        }

        if ($mode === 'customer') {
            return (bool) $request->session()->get(self::SESSION_CUSTOMER_ID);
        }

        return false;
    }

    public function mode(Request $request): ?string
    {
        return $request->session()->get(self::SESSION_MODE);
    }

    public function isGuest(Request $request): bool
    {
        return $this->mode($request) === 'guest';
    }

    public function customer(Request $request): ?User
    {
        if ($this->isGuest($request)) {
            return null;
        }

        $customerId = $request->session()->get(self::SESSION_CUSTOMER_ID);

        return $customerId ? User::find($customerId) : null;
    }

    public function pricingUser(Request $request): User
    {
        $customer = $this->customer($request);

        if ($customer) {
            return $customer;
        }

        $guest = new User();
        $guest->price_permission = true;

        return $guest;
    }

    public function startGuest(Request $request, int $adminId): void
    {
        $this->abandonPendingCart($request);

        $request->session()->put([
            self::SESSION_MODE => 'guest',
            self::SESSION_CUSTOMER_ID => null,
            self::SESSION_ADMIN_ID => $adminId,
            self::SESSION_LAST_ORDER_ID => null,
        ]);
    }

    public function startCustomer(Request $request, int $adminId, User $customer): void
    {
        $this->abandonPendingCart($request);

        $request->session()->put([
            self::SESSION_MODE => 'customer',
            self::SESSION_CUSTOMER_ID => $customer->id,
            self::SESSION_ADMIN_ID => $adminId,
            self::SESSION_LAST_ORDER_ID => null,
        ]);
    }

    public function clear(Request $request): void
    {
        $this->abandonPendingCart($request);

        $request->session()->forget([
            self::SESSION_MODE,
            self::SESSION_CUSTOMER_ID,
            self::SESSION_ADMIN_ID,
            self::SESSION_LAST_ORDER_ID,
        ]);
    }

    public function customerLabel(Request $request): string
    {
        if ($this->isGuest($request)) {
            return __('customers.pos.serving_guest');
        }

        return __('customers.pos.serving_customer', [
            'name' => optional($this->customer($request))->name ?? '-',
        ]);
    }

    public function productPrice(int $productId, Request $request): float
    {
        $customer = $this->customer($request);

        return $customer
            ? (float) Product::get_today_price($productId, $customer)
            : (float) Product::getPublicTodayPrice($productId);
    }

    public function currentCart(Request $request, bool $create = false): ?Cart
    {
        $sessionKey = $this->sessionKey($request);
        $customer = $this->customer($request);

        $query = Cart::query()
            ->where('session_id', $sessionKey)
            ->where('status', Cart::$status['pending']);

        if ($customer) {
            $query->where('user_id', $customer->id);
        } else {
            $query->whereNull('user_id');
        }

        $cart = $query->first();

        if (!$cart && $create) {
            $cart = Cart::create([
                'user_id' => $customer?->id,
                'session_id' => $sessionKey,
                'status' => Cart::$status['pending'],
            ]);
        }

        return $cart;
    }

    public function ownsCartProduct(Request $request, CartProduct $cartProduct): bool
    {
        $cart = $this->currentCart($request);

        return $cart && (int) $cartProduct->cart_id === (int) $cart->id;
    }

    public function cartCount(Request $request): int
    {
        $cart = $this->currentCart($request);

        if (!$cart) {
            return 0;
        }

        return (int) DB::table('cart_products')
            ->where('cart_id', $cart->id)
            ->where('status', CartProduct::$status['active'])
            ->count();
    }

    public function cartLines(Request $request): array
    {
        $cart = $this->currentCart($request);

        if (!$cart) {
            return [collect(), 0.0];
        }

        $user = $this->pricingUser($request);
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

        $total = 0.0;

        foreach ($products as $key => $value) {
            $images = $value->images ? json_decode($value->images, true) : null;
            $products[$key]->image_url = isset($images[0])
                ? url('/') . '/' . Product::$path . '/' . $value->product_id . '/' . $images[0]
                : asset('assets/images/product-default.jpg');
            $products[$key]->options = CartProduct::getOption($value->cart_product_id);
            $products[$key]->unit_price = $this->productPrice((int) $value->product_id, $request);
            $product = Product::find($value->product_id);
            $qty = ($value->quantity !== null && $value->quantity !== '') ? (float) $value->quantity : null;
            $weight = ($value->weight !== null && $value->weight !== '') ? (float) $value->weight : null;
            $linePrice = $product
                ? $product->calculateLinePrice((float) $products[$key]->unit_price, $qty, $weight)
                : 0;
            $products[$key]->price = $linePrice;
            $total += $linePrice;
        }

        return [$products, $total];
    }

    public function formatProduct(Product $product, Request $request): Product
    {
        $user = $this->pricingUser($request);
        $product->original_price = $product->price;
        $product->price = $this->productPrice((int) $product->id, $request);
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

    public function abandonPendingCart(Request $request): void
    {
        $cart = $this->currentCart($request);

        if ($cart) {
            $cart->update(['status' => Cart::$status['aborted']]);
        }
    }

    public function activeCustomers()
    {
        return User::query()
            ->where('status', User::$user_status['active'])
            ->whereNotNull('registration_completed_at')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'customer_type']);
    }
}
