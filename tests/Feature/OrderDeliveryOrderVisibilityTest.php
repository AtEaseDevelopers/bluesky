<?php

namespace Tests\Feature;

use App\Order;
use Tests\TestCase;

/**
 * Admins may view the delivery order (DO) for any order status, while the
 * customer-facing gate (canShowDeliveryOrder) stays restricted to the
 * fulfillment statuses.
 */
class OrderDeliveryOrderVisibilityTest extends TestCase
{
    /** @dataProvider allStatuses */
    public function test_admin_can_always_show_delivery_order(string $status): void
    {
        $order = new Order();
        $order->status = $status;

        $this->assertTrue(
            $order->canAdminShowDeliveryOrder(),
            "Admin should be able to view DO for status: {$status}"
        );
    }

    public function test_customer_facing_gate_stays_restricted(): void
    {
        $pending = new Order();
        $pending->status = Order::$status['pending'];
        $this->assertFalse($pending->canShowDeliveryOrder());

        $cancelled = new Order();
        $cancelled->status = Order::$status['cancelled'];
        $this->assertFalse($cancelled->canShowDeliveryOrder());

        $inRoute = new Order();
        $inRoute->status = Order::$status['in_route'];
        $this->assertTrue($inRoute->canShowDeliveryOrder());
    }

    public static function allStatuses(): array
    {
        return array_map(fn ($status) => [$status], array_values(Order::$status));
    }
}
