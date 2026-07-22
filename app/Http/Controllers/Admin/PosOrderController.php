<?php

namespace App\Http\Controllers\Admin;

use App\Cart;
use App\CartProduct;
use App\CartProductOption;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ValidatesProductCartInput;
use App\Order;
use App\OrderPayment;
use App\OrderProduct;
use App\OrderProductOption;
use App\Product;
use App\Services\OrderService;
use App\Services\PosService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOrderController extends Controller
{
    use ValidatesProductCartInput;

    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index(Request $request, PosService $pos)
    {
        $user = $pos->pricingUser($request);
        $keyword = $request->keyword;

        $products = Product::query()
            ->withStorefrontStock()
            ->storefrontAvailable()
            ->when($keyword, function ($q) use ($keyword) {
                $q->where('products.name', 'LIKE', '%' . $keyword . '%');
            })
            ->orderBy('products.nos')
            ->get()
            ->map(function ($product) use ($pos, $request) {
                return $pos->formatProduct($product, $request);
            });

        $cart = $pos->currentCart($request);
        $productsOutput = [];

        foreach ($products as $product) {
            $product->added_to_cart = null;

            if ($cart) {
                $product->added_to_cart = DB::table('cart_products')
                    ->select('cart_products.quantity', 'cart_products.weight')
                    ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
                    ->where('cart_products.status', CartProduct::$status['active'])
                    ->where('cart_products.product_id', $product->id)
                    ->where('cart_products.cart_id', $cart->id)
                    ->first();
            }

            $productsOutput[$product->id] = $product;
        }

        return view('admin.pos.products', [
            'user' => $user,
            'products' => $productsOutput,
            'keyword' => $keyword,
            'posReady' => $pos->isReady($request),
        ]);
    }

    public function addToCart(Request $request, PosService $pos, $id)
    {
        $this->ensurePosReady($request, $pos);

        $product = Product::query()
            ->withStorefrontStock()
            ->storefrontAvailable()
            ->where('products.id', $this->decryptId($id))
            ->firstOrFail();

        $data = $this->validateAddToCart($request, $product);
        if (isset($data['error']) && $data['error']) {
            return back()->withInput()->withErrors($data['field_err']);
        }

        $price = $pos->productPrice((int) $product->id, $request);
        $cart = $pos->currentCart($request, true);
        $linePrice = $product->calculateLinePrice(
            $price,
            isset($data['quantity']) ? (float) $data['quantity'] : null,
            isset($data['weight']) ? (float) $data['weight'] : null,
            true
        );

        $cartProduct = CartProduct::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $data['quantity'] ?? null,
            'weight' => $data['weight'] ?? null,
            'unit_price' => $price,
            'price' => $linePrice,
            'remark' => $data['remark'] ?? null,
            'status' => CartProduct::$status['active'],
        ]);

        foreach ($data['product_option'] ?? [] as $opt => $optVal) {
            if ($optVal) {
                CartProductOption::create([
                    'cart_product_id' => $cartProduct->id,
                    'option' => $opt,
                    'option_item' => $optVal,
                    'status' => CartProductOption::$status['active'],
                ]);
            }
        }

        return redirect()->route('admin.pos.cart')->with('success', __('customers.pos.item_added'));
    }

    public function addToCartProductInfo(Request $request, PosService $pos)
    {
        $this->ensurePosReady($request, $pos);

        $product = Product::query()
            ->withStorefrontStock()
            ->storefrontAvailable()
            ->where('products.id', $this->decryptId($request->input('id')))
            ->firstOrFail();

        $product = $pos->formatProduct($product, $request);
        $cart = $pos->currentCart($request);
        $cartProductOptions = collect();

        if ($cart) {
            $cartProductOptions = DB::table('cart_product_options')
                ->leftJoin('cart_products', 'cart_products.id', '=', 'cart_product_options.cart_product_id')
                ->where('cart_products.product_id', $product->id)
                ->where('cart_products.cart_id', $cart->id)
                ->where('cart_products.status', CartProduct::$status['active'])
                ->pluck('option_item', 'option');
        }

        return response()->json([
            'view' => view('member.includes.product_info', [
                'product' => $product,
                'product_option' => Product::getOption($product->id, true),
                'cart_product_options' => $cartProductOptions,
            ])->render(),
        ]);
    }

    public function cart(Request $request, PosService $pos)
    {
        $this->ensurePosReady($request, $pos);

        [$products, $total] = $pos->cartLines($request);

        return view('admin.pos.cart', [
            'user' => $pos->pricingUser($request),
            'products' => $products,
            'total' => number_format($total, 2, '.', ''),
        ]);
    }

    public function updateCartItem(Request $request, PosService $pos)
    {
        $this->ensurePosReady($request, $pos);

        $cartProduct = CartProduct::find($request->id);
        if ($cartProduct && $pos->ownsCartProduct($request, $cartProduct)) {
            $price = $pos->productPrice((int) $cartProduct->product_id, $request);
            $product = Product::find($cartProduct->product_id);
            $linePrice = $product
                ? $product->calculateLinePrice(
                    $price,
                    $request->quantity !== null ? (float) $request->quantity : null,
                    $request->weight !== null ? (float) $request->weight : null,
                    true
                )
                : $price * ($request->quantity ?? $request->weight);

            $cartProduct->update([
                'quantity' => $request->quantity ?? null,
                'weight' => $request->weight ?? null,
                'unit_price' => $price,
                'price' => $linePrice,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function removeCartItem(Request $request, PosService $pos, CartProduct $cart_product)
    {
        $this->ensurePosReady($request, $pos);

        if ($pos->ownsCartProduct($request, $cart_product)) {
            $cart_product->update(['status' => CartProduct::$status['removed']]);
        }

        return redirect()->route('admin.pos.cart')->with('success', __('customers.pos.item_removed'));
    }

    public function checkout(Request $request, PosService $pos)
    {
        $this->ensurePosReady($request, $pos);

        [$products, $total] = $pos->cartLines($request);

        if (!count($products)) {
            return redirect()->route('admin.pos.index')->with('error', __('customers.pos.cart_empty'));
        }

        $customer = $pos->customer($request) ?: new User();

        return view('admin.pos.checkout', [
            'user' => $pos->pricingUser($request),
            'customer' => $customer,
            'products' => $products,
            'total' => number_format($total, 2, '.', ''),
            'isGuest' => $pos->isGuest($request),
        ]);
    }

    public function submitCheckout(Request $request, PosService $pos, OrderService $orderService)
    {
        $this->ensurePosReady($request, $pos);

        $data = $this->validateCheckout($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        [$products, $total] = $pos->cartLines($request);
        if (!count($products)) {
            return redirect()->route('admin.pos.index')->with('error', __('customers.pos.cart_empty'));
        }

        $order = $this->createOrder($request, $pos, $products, $total, $data);
        $orderService->assignDoNumber($order);

        $action = $request->input('checkout_action', 'place_order');
        $request->session()->put(PosService::SESSION_LAST_ORDER_ID, $order->id);

        if ($action === 'make_payment') {
            return redirect()
                ->route('admin.pos.payment', $order->id)
                ->with('success', __('customers.pos.order_created_payment'));
        }

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('customers.pos.order_created', ['id' => $order->id]));
    }

    public function payment(Request $request, PosService $pos, $orderId)
    {
        $this->ensurePosReady($request, $pos);

        $order = Order::with('customer')->findOrFail($orderId);

        if ((int) $request->session()->get(PosService::SESSION_LAST_ORDER_ID) !== (int) $order->id) {
            return redirect()->route('admin.pos.index')->with('error', __('customers.pos.invalid_payment_order'));
        }

        return view('admin.pos.payment', [
            'order' => $order,
            'balanceDue' => $order->balanceDue(),
            'paymentMethods' => $order->allowedAdminPaymentMethods(),
            'requiresExactPayment' => $order->requiresExactPayment(),
        ]);
    }

    public function recordPayment(Request $request, PosService $pos, OrderService $orderService, $orderId)
    {
        $this->ensurePosReady($request, $pos);

        $order = Order::findOrFail($orderId);

        if ((int) $request->session()->get(PosService::SESSION_LAST_ORDER_ID) !== (int) $order->id) {
            return redirect()->route('admin.pos.index')->with('error', __('customers.pos.invalid_payment_order'));
        }

        $allowedMethods = array_keys($order->allowedAdminPaymentMethods());
        $proofMessages = OrderPayment::proofValidationMessages('payments.*.payment_proof');

        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:' . implode(',', $allowedMethods),
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.notes' => 'nullable|string|max:500',
            'payments.*.payment_proof' => OrderPayment::proofRules(false),
        ], $proofMessages);

        $payments = [];
        foreach ($request->input('payments', []) as $index => $row) {
            $payments[] = [
                'payment_method' => $row['payment_method'],
                'amount' => (float) $row['amount'],
                'notes' => $row['notes'] ?? null,
                'proof' => $request->file("payments.{$index}.payment_proof"),
            ];
        }

        try {
            $orderService->recordPosPayments(
                $order,
                $payments,
                Auth::guard('web_admin')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $request->session()->forget(PosService::SESSION_LAST_ORDER_ID);

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('customers.pos.payment_recorded', ['id' => $order->id]));
    }

    private function createOrder(Request $request, PosService $pos, $products, float $total, array $data): Order
    {
        $cart = $pos->currentCart($request);
        $cart->update(['status' => Cart::$status['completed']]);

        $customer = $pos->customer($request);
        $address = $data['shipping_address'] ?? $data['billing_address'];

        $order = Order::create([
            'user_id' => $customer?->id,
            'is_general' => $pos->isGuest($request),
            'order_type' => Order::$order_types['pos'],
            'cart_id' => $cart->id,
            'total_price' => $total,
            'subtotal' => $total,
            'attn_name' => $data['attn_name'],
            'attn_contact' => $data['attn_contact'],
            'billing_address' => trim((string) ($data['billing_address'] ?? '')),
            'shipping_address' => trim((string) ($address ?? '')),
            'payment_method' => $pos->isGuest($request) || !$customer || !$customer->isCreditCustomer() ? 'cod' : null,
            'status' => Order::$status['pending'],
            'payment_status' => Order::$payment_status['unpaid'],
            'fulfillment_type' => Order::$fulfillment_types['pickup'],
            'is_estimated' => true,
        ]);

        $orderWeight = 0;

        foreach ($products as $value) {
            $product = Product::find($value->product_id);
            $line = $product
                ? $product->resolveLineInputs(
                    $value->quantity !== null ? (float) $value->quantity : null,
                    $value->weight !== null ? (float) $value->weight : null,
                    true
                )
                : [
                    'quantity' => $value->quantity,
                    'weight' => $value->weight,
                    'product_weight' => $value->weight,
                    'order_weight' => $value->quantity ?? $value->weight,
                ];

            $orderProduct = OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $value->product_id,
                'product_name' => $value->name,
                'quantity' => $line['quantity'],
                'weight' => $line['weight'],
                'product_weight' => $line['product_weight'],
                'unit_price' => $value->unit_price,
                'price' => $value->price,
                'remark' => $value->remark,
                'status' => OrderProduct::$status['active'],
            ]);

            $orderWeight += $line['order_weight'];

            foreach ($value->options as $opt => $optItm) {
                if ($optItm) {
                    OrderProductOption::create([
                        'order_product_id' => $orderProduct->id,
                        'option' => $opt,
                        'option_item' => $optItm,
                        'status' => OrderProductOption::$status['active'],
                    ]);
                }
            }
        }

        $order->update(['order_weight' => $orderWeight]);

        return $order->fresh();
    }

    private function validateCheckout(Request $request)
    {
        $rules = [
            'attn_name' => ['required', 'string', 'max:30'],
            'attn_contact' => ['required', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string', 'max:100'],
            'shipping_address' => ['nullable', 'string', 'max:100'],
            'checkout_action' => ['required', 'in:place_order,make_payment'],
        ];

        try {
            return $request->validate($rules);
        } catch (ValidationException $err) {
            return ['error' => true, 'field_err' => $err->validator->errors()->getMessages()];
        }
    }

    private function ensurePosReady(Request $request, PosService $pos): void
    {
        if (!$pos->isReady($request)) {
            abort(403, __('customers.pos.setup_required'));
        }
    }

    private function decryptId($id)
    {
        try {
            return decrypt($id);
        } catch (\Exception $e) {
            return $id;
        }
    }
}
