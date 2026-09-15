<?php

namespace App\Services;

use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\PdfHelper;
use App\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderStatusService
{
    /**
     * Active statuses a cancelled order may be restored to. `completed` needs its
     * own settlement flow, so it is not offered as a manual restore target.
     */
    private const RESTORE_TARGETS = ['pending', 'packing', 'in_route', 'delivered'];

    /** Unified fulfilment flow for delivery, pickup, and courier. */
    private static array $transitions = [
        'pending' => ['packing', 'cancelled'],
        'packing' => ['in_route', 'cancelled'],
        'in_route' => ['delivered', 'cancelled'],
        'delivered' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => ['pending', 'packing', 'in_route', 'delivered'],
    ];

    public function transitionsFor(Order $order): array
    {
        if ($order->isPickup()) {
            return [
                'pending' => ['packing', 'cancelled'],
                'packing' => ['delivered', 'cancelled'],
                'in_route' => ['delivered', 'cancelled'],
                'delivered' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => ['pending', 'packing', 'in_route', 'delivered'],
            ];
        }

        return self::$transitions;
    }

    public function canTransition(Order $order, string $from, string $to): bool
    {
        if ($to === Order::$status['in_route']
            && ($order->isPickup() || ($order->isDelivery() && !$order->driver_id))) {
            return false;
        }

        return in_array($to, $this->transitionsFor($order)[$from] ?? [], true);
    }

    public function transition(Order $order, string $newStatus, ?int $adminId = null): Order
    {
        $previous = $order->status;

        if ($previous === $newStatus) {
            return $order;
        }

        // `cancelled` is not a normal step in the flow — restoring an order out of
        // it has to undo the cancellation side effects (credit reversal, stock),
        // so it runs through its own path rather than the transition side effects.
        if ($previous === Order::$status['cancelled']) {
            return $this->restoreFromCancelled($order, $newStatus, $adminId);
        }

        if (!$this->canTransition($order, $previous, $newStatus)) {
            throw new InvalidArgumentException(
                __('orders.invalid_status_transition', [
                    'from' => __('order.status.' . $previous),
                    'to' => __('order.status.' . $newStatus),
                ])
            );
        }

        if ($newStatus === Order::$status['in_route']
            && $order->isDelivery()
            && !$order->driver_id) {
            throw new InvalidArgumentException(__('orders.assign_driver_required'));
        }

        if ($newStatus === Order::$status['completed'] && !$order->isFullyPaid()) {
            throw new InvalidArgumentException(__('orders.payment_required_for_complete'));
        }

        if ($newStatus === Order::$status['completed'] && $order->requiresCreditSettlementBeforeComplete()) {
            throw new InvalidArgumentException(__('orders.credit_settlement_required_for_complete'));
        }

        $order->update(['status' => $newStatus]);

        if ($newStatus === Order::$status['packing']) {
            $order->update(['is_estimated' => false]);
            PdfHelper::GenerateOrderInvoice($order);
            PdfHelper::GenerateOrderInvoiceWithoutPrice($order);

            if (!$order->isDelivery()) {
                PdfHelper::GenerateDeliveryOrder($order->fresh());
            }
        }

        if ($newStatus === Order::$status['in_route']) {
            if ($order->isDelivery()) {
                PdfHelper::GenerateDeliveryOrder($order->fresh());
            }
        }

        if ($newStatus === Order::$status['completed']) {
            if (!$order->completed_at) {
                $order->update(['completed_at' => now()]);
            }

            $order = $order->fresh();
            if (!$order->invoice_number) {
                app(OrderService::class)->generateInvoiceNumber($order);
            }

            if (config('autocount.auto_sync_enabled') && $order->canSyncToAutoCount()) {
                app(AutoCountSyncService::class)->syncIfEligible($order->fresh(), $adminId);
            }
        }

        if ($newStatus === Order::$status['cancelled']) {
            app(CreditService::class)->reverseForOrder($order->fresh(), $adminId);
            PdfHelper::GenerateOrderInvoice($order);
            PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
        }

        app(StockService::class)->handleOrderStatusChange(
            $order->fresh(),
            $previous,
            $newStatus,
            $adminId
        );

        app(OrderService::class)->refreshPaymentStatus($order->fresh());

        return $order->fresh();
    }

    /**
     * Restore a cancelled order to an active status, undoing the two side effects
     * cancellation ran so the data stays consistent:
     *   - the credit reversal (voided customer-credit/credit-term payments and the
     *     `credit_reversal` ledger entry) is un-done so the customer owes again;
     *   - stock is re-deducted, but only for target statuses that would have
     *     deducted it in the normal flow (in_route onward) — restoring to
     *     pending/packing leaves stock untouched.
     * All writes run in one transaction: all-or-nothing.
     */
    public function restoreFromCancelled(Order $order, string $targetStatus, ?int $adminId = null): Order
    {
        if ($order->status !== Order::$status['cancelled']) {
            throw new InvalidArgumentException(__('orders.order_not_cancelled'));
        }

        if (!in_array($targetStatus, self::RESTORE_TARGETS, true)) {
            throw new InvalidArgumentException(
                __('orders.invalid_status_transition', [
                    'from' => __('order.status.' . $order->status),
                    'to' => __('order.status.' . $targetStatus),
                ])
            );
        }

        if ($targetStatus === Order::$status['in_route']
            && $order->isDelivery()
            && !$order->driver_id) {
            throw new InvalidArgumentException(__('orders.assign_driver_required'));
        }

        $orderId = $order->id;
        $customer = $order->user_id ? User::find($order->user_id) : null;
        $isCreditCustomer = $customer && $customer->isCreditCustomer();

        // credit_reversal is positive (it restored the balance on cancel); undoing
        // it re-applies the charge and re-confirms the payments cancel voided.
        $reversalTotal = 0.0;
        $voidedPayments = collect();
        if ($isCreditCustomer) {
            $reversalTotal = round((float) CustomerCreditLog::where('order_id', $orderId)
                ->where('type', 'credit_reversal')
                ->sum('amount'), 2);

            $voidedPayments = OrderPayment::where('order_id', $orderId)
                ->where('status', OrderPayment::STATUS_REJECTED)
                ->whereIn('payment_method', ['customer-credit', 'credit-term'])
                ->where('notes', 'like', 'Voided — order #' . $orderId . ' cancelled.%')
                ->get();
        }
        $hasCreditWork = $isCreditCustomer && abs($reversalTotal) >= 0.009;

        // Pending/packing never hold deducted stock in the normal flow.
        $shouldDeductStock = !in_array(
            $targetStatus,
            [Order::$status['pending'], Order::$status['packing']],
            true
        );

        DB::transaction(function () use ($order, $orderId, $targetStatus, $adminId, $hasCreditWork, $reversalTotal, $voidedPayments, $shouldDeductStock) {
            if ($hasCreditWork) {
                foreach ($voidedPayments as $payment) {
                    $payment->update([
                        'status' => OrderPayment::STATUS_CONFIRMED,
                        'notes' => 'Restored — order #' . $orderId . ' un-cancelled.',
                    ]);
                }

                // Drop the credit_reversal row and take the restored amount back off
                // the balance, keeping ledger-sum == balance.
                $customer = User::lockForUpdate()->find($order->user_id);
                $customer->update([
                    'credit_balance' => round((float) $customer->credit_balance - $reversalTotal, 2),
                ]);
                CustomerCreditLog::where('order_id', $orderId)
                    ->where('type', 'credit_reversal')
                    ->delete();
            }

            if ($shouldDeductStock) {
                // Guarded internally by orderAlreadyDeducted — safe to call.
                app(StockService::class)->deductForOrder($order->fresh(), $adminId);
            }

            $order->update(['status' => $targetStatus]);

            app(OrderService::class)->refreshPaymentStatus($order->fresh());
        });

        // Regenerate invoices so they no longer carry the cancelled state.
        $fresh = $order->fresh();
        PdfHelper::GenerateOrderInvoice($fresh);
        PdfHelper::GenerateOrderInvoiceWithoutPrice($fresh);

        return $order->fresh();
    }

    public function nextStatuses(Order $order): array
    {
        $statuses = $this->transitionsFor($order)[$order->status] ?? [];

        if (in_array(Order::$status['completed'], $statuses, true)
            && (!$order->isFullyPaid() || $order->requiresCreditSettlementBeforeComplete())) {
            $statuses = array_values(array_filter(
                $statuses,
                fn ($status) => $status !== Order::$status['completed']
            ));
        }

        if ($order->isPickup()) {
            $statuses = array_values(array_filter(
                $statuses,
                fn ($status) => $status !== Order::$status['in_route']
            ));
        }

        return $statuses;
    }
}
