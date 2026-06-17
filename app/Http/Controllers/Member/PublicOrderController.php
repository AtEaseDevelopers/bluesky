<?php

namespace App\Http\Controllers\Member;

use App\DeliverySlot;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\Product;
use App\PublicOrderLink;
use App\Services\PublicOrderCartService;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicOrderController extends Controller
{
    protected PublicOrderCartService $cartService;

    public function __construct(PublicOrderCartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function products($token)
    {
        $link = PublicOrderLink::where('token', $token)->firstOrFail();
        if (!$link->isActive()) {
            return view('public.order-expired');
        }

        $products = Product::query()
            ->select('products.*', 'product_stocks.quantity as stock_quantity', 'uoms.uom_name')
            ->join('product_stocks', 'product_stocks.product_id', '=', 'products.id')
            ->leftJoin('uoms', 'uoms.id', '=', 'products.uom_id')
            ->where('products.status', Product::$status['active'])
            ->where('product_stocks.quantity', '>', 0)
            ->orderBy('products.name')
            ->get()
            ->map(function ($product) {
                $product->stock_label = Product::formatStockQuantity(
                    (float) $product->stock_quantity,
                    $product->uom_name
                );
                $product->price_label = Product::formatUnitPrice(
                    (float) Product::resolvePrice($product->id),
                    $product->uom_name
                );
                $product->image_url = Product::resolveImageUrl($product);

                return $product;
            });

        return view('public.products', [
            'link' => $link,
            'products' => $products,
            'cartCount' => $this->cartService->count($token),
        ]);
    }

    public function addToCart(Request $request, $token, $productId)
    {
        $link = PublicOrderLink::where('token', $token)->firstOrFail();
        if (!$link->isActive()) {
            return view('public.order-expired');
        }

        $product = $this->findInStockProduct((int) $productId);
        $sellByWeight = $product->sell_in === 'weight' || $product->show_weight;
        $field = $sellByWeight ? 'weight' : 'quantity';

        $data = $request->validate([
            $field => 'required|numeric|min:0.001',
            'remark' => 'nullable|string|max:200',
        ]);

        $amount = (float) $data[$field];
        $stock = app(StockService::class)->getOrCreateStock($product->id);
        $inCart = $this->cartService->items($token)[$product->id] ?? null;
        $alreadyInCart = $inCart ? (float) ($inCart['quantity'] ?? 0) : 0;

        if ($amount + $alreadyInCart > (float) $stock->quantity) {
            return back()->with('error', 'Not enough stock. Only ' . Product::formatStockQuantity((float) $stock->quantity, $product->uom_name ?? 'KG') . ' available.');
        }

        $this->cartService->add($token, $product, $amount, $data['remark'] ?? null);

        return redirect(route('public.order', $link->token))
            ->with('success', $product->name . ' added to cart.');
    }

    public function cart($token)
    {
        $link = PublicOrderLink::where('token', $token)->firstOrFail();
        if (!$link->isActive()) {
            return view('public.order-expired');
        }

        $items = [];
        foreach ($this->cartService->items($token) as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 0);
            $items[] = (object) [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $qty,
                'weight' => $item['weight'] ?? null,
                'sell_by_weight' => $product->sell_in === 'weight' || $product->show_weight,
                'line_total' => (float) $product->price * $qty,
                'remark' => $item['remark'] ?? null,
            ];
        }

        return view('public.cart', [
            'link' => $link,
            'items' => $items,
            'subtotal' => $this->cartService->subtotal($token),
            'cartCount' => count($items),
        ]);
    }

    public function removeFromCart($token, $productId)
    {
        $link = PublicOrderLink::where('token', $token)->firstOrFail();
        if (!$link->isActive()) {
            return view('public.order-expired');
        }

        $this->cartService->remove($token, (int) $productId);

        return redirect(route('public.order.cart', $token))->with('success', 'Item removed from cart.');
    }

    public function checkoutForm($token)
    {
        $link = PublicOrderLink::where('token', $token)->firstOrFail();
        if (!$link->isActive()) {
            return view('public.order-expired');
        }

        if ($this->cartService->count($token) === 0) {
            return redirect(route('public.order', $token))
                ->with('error', 'Please add products to your cart before checkout.');
        }

        return view('public.checkout', [
            'link' => $link,
            'deliverySlots' => DeliverySlot::availableSlots(),
            'subtotal' => $this->cartService->subtotal($token),
            'cartCount' => $this->cartService->count($token),
        ]);
    }

    public function store(Request $request, $token)
    {
        $link = PublicOrderLink::where('token', $token)->firstOrFail();

        if (!$link->isActive()) {
            return view('public.order-expired');
        }

        if ($this->cartService->count($token) === 0) {
            return redirect(route('public.order', $token))
                ->with('error', 'Your cart is empty.');
        }

        $data = $request->validate([
            'walk_in_name' => 'required|string|max:100',
            'walk_in_phone' => 'required|string|max:30',
            'shipping_address' => 'required|string|max:200',
            'delivery_slot_id' => 'required|exists:delivery_slots,id',
        ]);

        $slot = DeliverySlot::findOrFail($data['delivery_slot_id']);
        if (!$slot->isAvailable()) {
            return back()->withInput()->with('error', 'Selected delivery slot is no longer available.');
        }

        $cartItems = $this->cartService->items($token);
        $subtotal = $this->cartService->subtotal($token);

        $order = DB::transaction(function () use ($link, $data, $slot, $cartItems, $subtotal, $token) {
            $link = PublicOrderLink::where('token', $link->token)->lockForUpdate()->firstOrFail();

            if (!$link->isActive()) {
                return null;
            }

            $order = Order::create([
                'user_id' => null,
                'order_type' => Order::$order_types['public'],
                'walk_in_name' => $data['walk_in_name'],
                'walk_in_phone' => $data['walk_in_phone'],
                'cart_id' => null,
                'subtotal' => $subtotal,
                'total_price' => $subtotal,
                'delivery_fee' => 0,
                'billing_address' => $data['shipping_address'],
                'shipping_address' => $data['shipping_address'],
                'payment_method' => json_encode(['cod']),
                'payment_status' => Order::$payment_status['unpaid'],
                'status' => Order::$status['pending'],
                'delivery_slot_id' => $slot->id,
                'delivery_date' => $slot->slot_date,
                'delivery_time_slot' => $slot->time_label,
                'is_estimated' => true,
            ]);

            foreach ($cartItems as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = (float) ($item['quantity'] ?? 1);
                $weight = $item['weight'] ?? null;

                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'weight' => $weight,
                    'product_weight' => $weight,
                    'unit_price' => $product->price,
                    'price' => (float) $product->price * $qty,
                    'status' => OrderProduct::$status['active'],
                ]);
            }

            $link->update([
                'order_id' => $order->id,
                'used_at' => now(),
            ]);

            app(PublicOrderCartService::class)->clear($token);

            app(OrderService::class)->assignDoNumber($order);

            return $order;
        });

        if (!$order) {
            return view('public.order-expired');
        }

        return view('public.order-success', ['order' => $order]);
    }

    private function findInStockProduct(int $productId): Product
    {
        return Product::query()
            ->select('products.*', 'product_stocks.quantity as stock_quantity', 'uoms.uom_name')
            ->join('product_stocks', 'product_stocks.product_id', '=', 'products.id')
            ->leftJoin('uoms', 'uoms.id', '=', 'products.uom_id')
            ->where('products.id', $productId)
            ->where('products.status', Product::$status['active'])
            ->where('product_stocks.quantity', '>', 0)
            ->firstOrFail();
    }
}
