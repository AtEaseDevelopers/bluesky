<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Order;
use App\OrderPayment;
use App\Services\CreditService;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
     * Mark the selected credit orders as paid. Each ticked order carries its own
     * settlement line — how much the customer paid, by which method, and an
     * optional proof upload (mirrors the order "record payment" flow). The amount
     * settles that order's credit-term charge on the ledger; a full settlement
     * moves the order to completed, a partial one leaves the remainder on credit.
     * Orders settle independently, so the admin can clear them one at a time.
     */
    public function markPaid(Request $request, $id)
    {
        $customer = User::findOrFail(decrypt($id));

        if (!$customer->isCreditCustomer()) {
            return back()->with('error', 'Settling balances applies to credit customers only.');
        }

        $orderIds = array_values(array_unique(array_map('intval', (array) $request->input('order_ids', []))));

        $rules = [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer'],
        ];
        $settlementMethods = array_keys(OrderPayment::settlementMethods());
        foreach ($orderIds as $orderId) {
            $rules["payments.{$orderId}.amount"] = ['required', 'numeric', 'min:0.01'];
            $rules["payments.{$orderId}.payment_method"] = ['required', Rule::in($settlementMethods)];
            $rules["payments.{$orderId}.payment_proof"] = OrderPayment::proofRules(false);
        }

        $request->validate($rules, array_merge(
            ['order_ids.required' => 'Select at least one order to mark as paid.'],
            OrderPayment::proofValidationMessages('payments.*.payment_proof')
        ));

        $orders = Order::where('user_id', $customer->id)
            ->carriedOnCredit()
            ->whereIn('id', $orderIds)
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'No matching credit orders to settle.');
        }

        $adminId = Auth::guard('web_admin')->id();
        $completed = 0;
        $settled = 0;

        foreach ($orders as $order) {
            // Nothing owed on this order — skip so we never store an orphan proof.
            if ($order->creditOutstandingAmount() <= 0.009) {
                continue;
            }

            $line = $request->input("payments.{$order->id}", []);
            $proofPath = $this->storeSettlementProof(
                $order,
                $request->file("payments.{$order->id}.payment_proof")
            );

            $log = app(CreditService::class)->settleOrderCredit(
                $order->fresh(),
                $adminId,
                null,
                isset($line['amount']) ? (float) $line['amount'] : null,
                $line['payment_method'] ?? null,
                $proofPath
            );

            if ($log) {
                $settled++;
                // Re-derive the order's payment status now the ledger moved:
                // full settlement reads as paid, a partial one as partially paid.
                app(OrderService::class)->refreshPaymentStatus($order->fresh());
            }

            try {
                app(OrderStatusService::class)->transition(
                    $order->fresh(),
                    Order::$status['completed'],
                    $adminId
                );
                $completed++;
            } catch (\InvalidArgumentException $e) {
                // Ledger settlement stands even if the order can't complete yet
                // (e.g. a partial payment leaves an outstanding balance).
            }
        }

        return back()->with('success', "{$settled} credit order(s) settled, {$completed} marked as completed.");
    }

    /** Persist an uploaded settlement proof under the order's payments folder. */
    private function storeSettlementProof(Order $order, $proof): ?string
    {
        if (!$proof) {
            return null;
        }

        OrderPayment::assertValidProof($proof, false);

        $filename = time() . rand() . '.' . $proof->getClientOriginalExtension();
        $path = Order::$path . '/' . $order->id . '/payments';
        Storage::disk('local')->put($path . '/' . $filename, file_get_contents($proof));

        return $filename;
    }
}
