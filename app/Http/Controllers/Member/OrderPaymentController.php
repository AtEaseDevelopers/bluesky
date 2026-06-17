<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OrderPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function store(Request $request, $orderId)
    {
        $order = $this->findOwnedOrder($orderId);
        $user = Auth::guard('web')->user();
        $allowedMethods = array_keys($order->allowedCustomerPaymentMethods());

        $data = $request->validate([
            'payment_method' => 'required|in:' . implode(',', $allowedMethods),
            'amount' => 'required|numeric|min:0.01',
            'payment_proof' => OrderPayment::proofRules(true),
            'notes' => 'nullable|string|max:500',
        ], OrderPayment::proofValidationMessages());

        try {
            app(OrderService::class)->submitCustomerPaymentProof(
                $order,
                $user,
                $data['payment_method'],
                (float) $data['amount'],
                $request->file('payment_proof'),
                $data['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment proof submitted. Our team will review and confirm your payment.');
    }

    public function viewProof($orderId, $paymentId)
    {
        $order = $this->findOwnedOrder($orderId);
        $payment = OrderPayment::where('order_id', $order->id)
            ->where('id', $paymentId)
            ->where('submitted_by_user_id', Auth::guard('web')->id())
            ->firstOrFail();

        if (!$payment->payment_proof) {
            abort(404, 'Payment proof not found.');
        }

        $path = Order::$path . '/' . $order->id . '/payments/' . basename($payment->payment_proof);

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Payment proof not found.');
        }

        return response(Storage::disk('local')->get($path))
            ->header('Content-Type', Storage::disk('local')->mimeType($path));
    }

    private function findOwnedOrder($encryptedId): Order
    {
        $order = Order::findOrFail(decrypt($encryptedId));
        $user = Auth::guard('web')->user();

        if ((int) $order->user_id !== (int) $user->id) {
            abort(403);
        }

        return $order;
    }
}
