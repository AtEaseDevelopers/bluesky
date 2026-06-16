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
        $order = Order::findOrFail($id);

        $data = $request->validate([
            'payment_method' => 'required|in:' . implode(',', array_keys(OrderPayment::$payment_methods)),
            'amount' => 'required|numeric|min:0.01',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            app(OrderService::class)->recordPayment(
                $order,
                $data['payment_method'],
                (float) $data['amount'],
                $request->file('payment_proof'),
                $data['notes'] ?? null,
                Auth::guard('web_admin')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded and knocked off against order balance.');
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
