<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\OrderPayment;
use App\Services\BulkPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BulkPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function index()
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->isCreditCustomer()) {
            return redirect()->route('member.orders')->with('error', 'Bulk payment is available for credit customers only.');
        }

        $orders = app(BulkPaymentService::class)->openOrdersFor($user);

        return view('member.bulk-payments', [
            'user' => $user,
            'orders' => $orders,
            'paymentMethods' => OrderPayment::$credit_customer_methods,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'payment_method' => 'required|in:' . implode(',', array_keys(OrderPayment::$credit_customer_methods)),
            'amount' => 'required|numeric|min:0.01',
            'payment_proof' => OrderPayment::proofRules(true),
            'notes' => 'nullable|string|max:500',
        ], OrderPayment::proofValidationMessages());

        try {
            app(BulkPaymentService::class)->submit(
                $user,
                $data['order_ids'],
                $data['payment_method'],
                (float) $data['amount'],
                $request->file('payment_proof'),
                $data['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('member.orders')->with(
            'success',
            'Bulk payment submitted. Our team will review and knock off your selected invoices.'
        );
    }
}
