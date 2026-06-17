<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OrderPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function store(Request $request, $id)
    {
        $order = Order::with('customer')->findOrFail($id);

        if ($order->status === Order::$status['cancelled']) {
            return back()->with('error', 'Payments cannot be recorded on a cancelled order.');
        }

        if (!$order->canRecordAdminPayment()) {
            return back()->with('error', $order->isCodCustomer()
                ? 'COD payment can only be recorded when the order is in route or delivered.'
                : 'Payment cannot be recorded for this order in its current status.');
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
            app(OrderService::class)->recordPayments(
                $order,
                $payments,
                Auth::guard('web_admin')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $count = count($payments);
        $message = $count === 1
            ? 'Payment recorded and knocked off against order balance.'
            : "{$count} payments recorded (split payment).";

        return back()->with('success', $message);
    }

    public function confirm(Request $request, $orderId, $paymentId)
    {
        $order = Order::findOrFail($orderId);
        $payment = OrderPayment::where('order_id', $order->id)->findOrFail($paymentId);

        try {
            app(OrderService::class)->confirmPendingPayment(
                $payment,
                Auth::guard('web_admin')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Customer payment proof confirmed and applied to order balance.');
    }

    public function reject(Request $request, $orderId, $paymentId)
    {
        $order = Order::findOrFail($orderId);
        $payment = OrderPayment::where('order_id', $order->id)->findOrFail($paymentId);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            app(OrderService::class)->rejectPendingPayment(
                $payment,
                Auth::guard('web_admin')->id(),
                $data['reason'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Customer payment submission rejected.');
    }

    public function viewProof($orderId, $filename)
    {
        $order = Order::findOrFail($orderId);
        $path = Order::$path . '/' . $order->id . '/payments/' . basename($filename);

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Payment proof not found.');
        }

        return response(Storage::disk('local')->get($path))
            ->header('Content-Type', Storage::disk('local')->mimeType($path));
    }

    public function syncAutoCount($id)
    {
        $order = Order::findOrFail($id);

        try {
            app(\App\Services\AutoCountSyncService::class)->syncIfEligible(
                $order,
                Auth::guard('web_admin')->id()
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice queued for AutoCount sync.');
    }

    public function complete(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->balanceDue() > 0) {
            return back()->with('error', 'Order cannot be completed until full payment is received.');
        }

        if ($order->status !== Order::$status['delivered']) {
            return back()->with('error', 'Order must be delivered before completion.');
        }

        app(OrderStatusService::class)->transition(
            $order,
            Order::$status['paid_completed'],
            Auth::guard('web_admin')->id()
        );

        return back()->with('success', 'Order marked as paid and completed.');
    }
}
