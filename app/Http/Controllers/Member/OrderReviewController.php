<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function show($id)
    {
        $order = $this->findOwnedOrder($id);

        if ($order->status !== Order::$status['customer_reviewing']) {
            return redirect(url('order/summary/' . $id))
                ->with('warning', 'This order is not awaiting your review.');
        }

        $products = OrderProduct::where('order_id', $order->id)
            ->where('status', OrderProduct::$status['active'])
            ->get();

        return view('member.orders.review', [
            'order' => $order,
            'products' => $products,
            'encryptedId' => $id,
            'invoiceUrl' => url('/') . '/' . Order::$path . '/' . $order->id . '/invoice-' . $order->id . '.pdf',
        ]);
    }

    public function approve(Request $request, $id)
    {
        $order = $this->findOwnedOrder($id);

        if ($order->status !== Order::$status['customer_reviewing']) {
            return back()->with('error', 'This order is no longer awaiting your approval.');
        }

        try {
            app(OrderStatusService::class)->transition($order, Order::$status['in_route']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect(url('order/summary/' . $id))
            ->with('success', 'Order approved. Your order is now in route for delivery.');
    }

    public function reject(Request $request, $id)
    {
        $order = $this->findOwnedOrder($id);

        if ($order->status !== Order::$status['customer_reviewing']) {
            return back()->with('error', 'This order is no longer awaiting your review.');
        }

        try {
            app(OrderStatusService::class)->transition($order, Order::$status['cancelled']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect(route('member.orders'))
            ->with('success', 'Order #' . $order->id . ' has been cancelled.');
    }

    private function findOwnedOrder(string $encryptedId): Order
    {
        $order = Order::findOrFail(decrypt($encryptedId));
        $user = Auth::guard('web')->user();

        if ($user->id != $order->user_id) {
            abort(404);
        }

        return $order;
    }
}
