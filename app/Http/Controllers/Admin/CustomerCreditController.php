<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Order;
use App\Services\CreditService;
use App\Services\OrderStatusService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerCreditController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function adjust(Request $request, $id)
    {
        $customer = User::findOrFail(decrypt($id));

        if (!$customer->isCreditCustomer()) {
            return back()->with('error', 'Credit adjustments apply to credit customers only. COD customers pay on delivery.');
        }

        $data = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'notes' => 'required|string|max:500',
        ]);

        try {
            app(CreditService::class)->manualAdjust(
                $customer,
                (float) $data['amount'],
                $data['notes'],
                Auth::guard('web_admin')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Customer credit balance updated.');
    }

    /**
     * Mark the selected credit orders as paid: settle each order's own credit-term
     * amount on the ledger and move it to completed. Orders settle independently,
     * so the admin can clear them one at a time.
     */
    public function markPaid(Request $request, $id)
    {
        $customer = User::findOrFail(decrypt($id));

        if (!$customer->isCreditCustomer()) {
            return back()->with('error', 'Settling balances applies to credit customers only.');
        }

        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer'],
        ], [
            'order_ids.required' => 'Select at least one order to mark as paid.',
        ]);

        $orders = Order::where('user_id', $customer->id)
            ->where('status', Order::$status['credit'])
            ->whereIn('id', $data['order_ids'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'No matching credit orders to settle.');
        }

        $adminId = Auth::guard('web_admin')->id();
        $completed = 0;

        foreach ($orders as $order) {
            app(CreditService::class)->settleOrderCredit($order->fresh(), $adminId);

            try {
                app(OrderStatusService::class)->transition(
                    $order->fresh(),
                    Order::$status['completed'],
                    $adminId
                );
                $completed++;
            } catch (\InvalidArgumentException $e) {
                // Ledger settlement stands even if the order can't complete yet.
            }
        }

        return back()->with('success', "{$completed} credit order(s) settled and marked as completed.");
    }
}
