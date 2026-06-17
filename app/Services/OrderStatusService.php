<?php

namespace App\Services;

use App\Order;
use App\PdfHelper;
use InvalidArgumentException;

class OrderStatusService
{
    private static array $transitions = [
        'pending' => ['customer_reviewing', 'cancelled'],
        'customer_reviewing' => ['in_route', 'cancelled'],
        'in_route' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::$transitions[$from] ?? [], true);
    }

    public function transition(Order $order, string $newStatus, ?int $adminId = null): Order
    {
        $previous = $order->status;

        if ($previous === $newStatus) {
            return $order;
        }

        if (!$this->canTransition($previous, $newStatus)) {
            throw new InvalidArgumentException(
                "Cannot change order status from {$previous} to {$newStatus}."
            );
        }

        $order->update(['status' => $newStatus]);

        if ($newStatus === Order::$status['customer_reviewing']) {
            $order->update(['is_estimated' => false]);
            PdfHelper::GenerateOrderInvoice($order);
            PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
        }

        if ($newStatus === Order::$status['in_route']) {
            PdfHelper::GenerateDeliveryOrder($order->fresh());
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

    public function nextStatuses(string $current): array
    {
        return self::$transitions[$current] ?? [];
    }
}
