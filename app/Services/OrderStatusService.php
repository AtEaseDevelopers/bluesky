<?php

namespace App\Services;

use App\Order;
use App\PdfHelper;
use InvalidArgumentException;

class OrderStatusService
{
    private static array $deliveryTransitions = [
        'pending' => ['packing', 'cancelled'],
        'packing' => ['in_route', 'cancelled'],
        'customer_reviewing' => ['packing', 'in_route', 'cancelled'],
        'in_route' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    private static array $posTransitions = [
        'pending' => ['packing', 'cancelled'],
        'packing' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function transitionsFor(Order $order): array
    {
        return $order->isPosOrder() ? self::$posTransitions : self::$deliveryTransitions;
    }

    public function canTransition(Order $order, string $from, string $to): bool
    {
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
                "Cannot change order status from {$previous} to {$newStatus}."
            );
        }

        $order->update(['status' => $newStatus]);

        if ($newStatus === Order::$status['packing']) {
            $order->update(['is_estimated' => false]);
            PdfHelper::GenerateOrderInvoice($order);
            PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
        }

        if ($newStatus === Order::$status['in_route']) {
            PdfHelper::GenerateDeliveryOrder($order->fresh());
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
        return $this->transitionsFor($order)[$order->status] ?? [];
    }
}
