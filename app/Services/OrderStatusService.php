<?php

namespace App\Services;

use App\Order;
use App\PdfHelper;
use InvalidArgumentException;

class OrderStatusService
{
    /** Unified fulfilment flow for delivery, pickup, and courier. */
    private static array $transitions = [
        'pending' => ['packing', 'cancelled'],
        'packing' => ['in_route', 'cancelled'],
        'in_route' => ['delivered', 'on_hold', 'cancelled'],
        'on_hold' => ['in_route', 'cancelled'],
        'delivered' => ['credit', 'completed', 'cancelled'],
        'credit' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function transitionsFor(Order $order): array
    {
        if ($order->isPickup()) {
            return [
                'pending' => ['packing', 'cancelled'],
                'packing' => ['delivered', 'on_hold', 'cancelled'],
                'in_route' => ['delivered', 'on_hold', 'cancelled'],
                'on_hold' => ['delivered', 'cancelled'],
                'delivered' => ['credit', 'completed', 'cancelled'],
                'credit' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => [],
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

        if ($newStatus === Order::$status['credit']
            && !($order->isCreditCustomer() && $order->hasConfirmedCreditTermPayment())) {
            throw new InvalidArgumentException(__('orders.credit_status_requires_credit_term'));
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

            if ($order->canSyncToAutoCount()) {
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

        if ($newStatus === Order::$status['delivered']) {
            return $this->maybeEnterCredit($order->fresh(), $adminId);
        }

        return $order->fresh();
    }

    /**
     * A delivered credit order carrying a "buy now, pay later" charge is parked
     * in the credit holding state until the customer settles their balance.
     */
    public function maybeEnterCredit(Order $order, ?int $adminId = null): Order
    {
        if ($order->status === Order::$status['delivered']
            && $order->isCreditCustomer()
            && $order->hasConfirmedCreditTermPayment()) {
            return $this->transition($order, Order::$status['credit'], $adminId);
        }

        return $order;
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

        // Credit is an automatic holding state, never a manual action.
        $statuses = array_values(array_filter(
            $statuses,
            fn ($status) => $status !== Order::$status['credit']
        ));

        if ($order->isPickup()) {
            $statuses = array_values(array_filter(
                $statuses,
                fn ($status) => $status !== Order::$status['in_route']
            ));
        }

        return $statuses;
    }
}
