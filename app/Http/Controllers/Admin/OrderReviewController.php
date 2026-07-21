<?php

namespace App\Http\Controllers\Admin;

use App\Driver;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\Product;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function show($id)
    {
        $order = Order::with('customer')->findOrFail($id);

        if (!Order::canAdjustQuantities($order->status)) {
            return redirect(route('admin.orders.summary', $order->id))
                ->with('warning', 'This order can no longer be adjusted.');
        }

        $products = OrderProduct::query()
            ->select('order_products.*', 'products.sell_in')
            ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            ->where('order_products.order_id', $order->id)
            ->where('order_products.status', OrderProduct::$status['active'])
            ->get();

        $drivers = Driver::optionsForSelect($order->driver_id ? (int) $order->driver_id : null);

        return view('admin.orders.review', [
            'order' => $order,
            'products' => $products,
            'drivers' => $drivers,
            'customerName' => app(OrderService::class)->displayCustomerName($order),
            'canAdjustAmount' => app(OrderService::class)->canAdjustAmount(Auth::guard('web_admin')->user()),
            'isCreditCustomer' => $order->isCreditCustomer(),
            'isPosOrder' => $order->isPosOrder(),
            'defaultPaymentDueDate' => app(OrderService::class)->resolvePaymentDueDate($order, null),
        ]);
    }

    public function store(Request $request, $id)
    {
        $order = Order::with('customer')->findOrFail($id);

        if (!Order::canAdjustQuantities($order->status)) {
            return back()->with('error', 'This order can no longer be adjusted.');
        }

        $rules = [
            'delivery_fee' => 'required|numeric|min:0',
            'amount_adjustment' => 'nullable|numeric',
            'adjustment_remark' => 'nullable|string|max:500',
            'payment_due_date' => 'nullable|date',
            'line_items' => 'required|array',
        ];

        if (!$order->isInStoreOrder() && !$order->isPosOrder()) {
            $rules['driver_id'] = 'nullable|exists:drivers,id';
            $rules['fulfillment_type'] = 'required|in:delivery,pickup';
        }

        $orderProducts = OrderProduct::query()
            ->select('order_products.id', 'order_products.product_id')
            ->where('order_products.order_id', $order->id)
            ->whereIn('order_products.id', array_keys($request->input('line_items', [])))
            ->get()
            ->keyBy('id');

        foreach ($request->input('line_items', []) as $lineId => $item) {
            $orderProduct = $orderProducts->get((int) $lineId);
            if (!$orderProduct) {
                continue;
            }

            $product = Product::find($orderProduct->product_id);
            if (!$product) {
                continue;
            }

            if ($product->requiresQuantityInput()) {
                $rules['line_items.' . $lineId . '.quantity'] = 'required|numeric|min:0.001';
            } else {
                $rules['line_items.' . $lineId . '.quantity'] = 'nullable';
            }

            if ($product->sell_in === Product::SELL_IN_WEIGHT) {
                $rules['line_items.' . $lineId . '.weight'] = 'required|numeric|min:0.001';
            } elseif ($product->sell_in === Product::SELL_IN_QTY_BILL_WEIGHT) {
                $rules['line_items.' . $lineId . '.weight'] = 'nullable|numeric|min:0';
            } else {
                $rules['line_items.' . $lineId . '.weight'] = 'nullable|numeric|min:0';
            }
        }

        $request->validate($rules);

        $orderService = app(OrderService::class);
        $amountAdjustment = $request->input('amount_adjustment', 0);

        if (!$orderService->canAdjustAmount(Auth::guard('web_admin')->user())) {
            $amountAdjustment = $order->amount_adjustment;
        }

        $wasPending = $order->status === Order::$status['pending'];

        $orderService->applyReviewAdjustments(
            $order,
            $request->input('line_items', []),
            [
                'delivery_fee' => $request->input('delivery_fee'),
                'amount_adjustment' => $amountAdjustment,
                'adjustment_remark' => $request->input('adjustment_remark'),
                'payment_due_date' => $request->input('payment_due_date'),
                'driver_id' => $order->isInStoreOrder()
                    ? null
                    : $request->input('driver_id'),
                'fulfillment_type' => $order->isInStoreOrder()
                    ? Order::$fulfillment_types['pickup']
                    : $request->input('fulfillment_type'),
                'send_to_customer' => $request->input('send_to_customer') === '1',
            ],
            Auth::guard('web_admin')->id()
        );

        $message = $request->boolean('send_to_customer')
            ? ($wasPending
                ? __('orders.review_moved_to_packing')
                : __('orders.review_invoice_updated'))
            : __('orders.review_adjustments_saved');

        return redirect(route('admin.orders.summary', $order->id))->with('success', $message);
    }
}
