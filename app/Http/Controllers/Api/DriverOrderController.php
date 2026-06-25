<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Order;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;

class DriverOrderController extends Controller
{
    public function index(Request $request)
    {
        $driver = $request->attributes->get('driver');

        $orders = Order::with(['customer:id,name,attn_contact,customer_type'])
            ->where('driver_id', $driver->id)
            ->where('fulfillment_type', Order::$fulfillment_types['delivery'])
            ->whereIn('status', [
                Order::$status['in_route'],
                Order::$status['delivered'],
            ])
            ->orderByDesc('id')
            ->get()
            ->map(function (Order $order) {
                return $this->formatOrder($order);
            });

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, $id)
    {
        $driver = $request->attributes->get('driver');
        $order = $this->findDriverOrder($driver->id, $id);

        return response()->json([
            'success' => true,
            'order' => $this->formatOrder($order->load(['customer:id,name,attn_contact,customer_type', 'orderProducts'])),
        ]);
    }

    public function collectCod(Request $request, $id)
    {
        $driver = $request->attributes->get('driver');
        $order = $this->findDriverOrder($driver->id, $id);

        if (!$order->isCodCustomer()) {
            return response()->json([
                'success' => false,
                'message' => 'COD collection applies to COD customers only. Credit customers pay by their payment due date.',
            ], 422);
        }

        if (!in_array($order->status, [
            Order::$status['in_route'],
            Order::$status['delivered'],
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'COD collection is only allowed for in-route or delivered orders.',
            ], 422);
        }

        $data = $request->validate([
            'payment_method' => 'required|in:' . implode(',', array_keys(\App\OrderPayment::$cod_admin_methods)),
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
            'payment_proof' => \App\OrderPayment::proofRules(false),
        ], \App\OrderPayment::proofValidationMessages());

        $balanceDue = $order->balanceDue();
        if (abs((float) $data['amount'] - $balanceDue) > 0.009) {
            return response()->json([
                'success' => false,
                'message' => 'COD orders require the exact balance due: RM ' . number_format($balanceDue, 2) . '.',
            ], 422);
        }

        try {
            app(OrderService::class)->recordPayment(
                $order,
                $data['payment_method'],
                (float) $data['amount'],
                $request->file('payment_proof'),
                $data['notes'] ?? null,
                null,
                $driver->id
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $order = $order->fresh();

        if ($order->status === Order::$status['in_route'] && $order->balanceDue() <= 0) {
            try {
                app(OrderStatusService::class)->transition(
                    $order,
                    Order::$status['delivered'],
                    null
                );
                $order = $order->fresh();
            } catch (\InvalidArgumentException $e) {
                // Keep payment even if status transition fails.
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'COD payment recorded.',
            'order' => $this->formatOrder($order->load('customer:id,name,attn_contact,customer_type')),
        ]);
    }

    private function findDriverOrder(int $driverId, $orderId): Order
    {
        return Order::where('id', $orderId)
            ->where('driver_id', $driverId)
            ->where('fulfillment_type', Order::$fulfillment_types['delivery'])
            ->firstOrFail();
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total_price' => (float) $order->total_price,
            'paid_amount' => (float) $order->paid_amount,
            'balance_due' => $order->balanceDue(),
            'payment_methods' => $order->paymentMethodsLabel(),
            'delivery_date' => optional($order->delivery_date)->format('Y-m-d'),
            'delivery_time_slot' => $order->delivery_time_slot,
            'shipping_address' => $order->shipping_address,
            'customer_name' => $order->customer->name ?? $order->walk_in_name,
            'customer_phone' => $order->customer->attn_contact ?? $order->walk_in_phone,
            'customer_type' => $order->customer->customer_type ?? 'cod',
            'products' => $order->relationLoaded('orderProducts')
                ? $order->orderProducts->map(function ($item) {
                    return [
                        'name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'weight' => $item->weight,
                        'price' => (float) $item->price,
                    ];
                })->values()
                : [],
        ];
    }
}
