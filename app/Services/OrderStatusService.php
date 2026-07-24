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
        'in_route' => ['delivered', 'cancelled'],
        'delivered' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function transitionsFor(Order $order): array
    {
        return self::$transitions;
    }

    public function canTransition(Order $order, string $from, string $to): bool
    {
        if ($to === Order::$status['in_route']
            && $order->isDelivery()
            && !$order->driver_id) {
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

        if ($newStatus === Order::$status['completed'] && !$order->isFullyPaid()) {
            throw new InvalidArgumentException(__('orders.payment_required_for_complete'));
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
        }

        if ($newStatus === Order::$status['cancelled']) {
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

    public function nextStatuses(Order $order): array
    {
        $statuses = $this->transitionsFor($order)[$order->status] ?? [];

        if (in_array(Order::$status['completed'], $statuses, true) && !$order->isFullyPaid()) {
            $statuses = array_values(array_filter(
                $statuses,
                fn ($status) => $status !== Order::$status['completed']
            ));
        }

        return $statuses;
    }
}
