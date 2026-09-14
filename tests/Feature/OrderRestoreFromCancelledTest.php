<?php

namespace Tests\Feature;

use App\Admin;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\OrderProduct;
use App\Product;
use App\ProductStock;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use App\StockMovement;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderRestoreFromCancelledTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeCreditCustomer(float $balance = 0): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood ' . rand(1000, 9999),
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'credit_balance' => $balance,
            'status' => 'active',
            'payment_method' => json_encode(['credit-term']),
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeCodCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Walk In ' . rand(1000, 9999),
            'email' => 'cod' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'normal',
            'customer_type' => 'normal',
            'credit_balance' => 0,
            'status' => 'active',
            'payment_method' => json_encode(['cash']),
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(User $customer, array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'delivered',
            'fulfillment_type' => 'delivery',
            'driver_id' => null,
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    private function makeQtyProduct(int $stock = 100): Product
    {
        $product = Product::forceCreate([
            'name' => 'Prawn ' . rand(1000, 9999),
            'sku' => 'SKU' . rand(1000, 9999),
            'price' => 10.00,
            'weight' => 0,
            'status' => Product::$status['active'],
            'images' => json_encode(['prawn.jpg']),
            'sell_in' => Product::SELL_IN_QTY,
        ]);

        ProductStock::forceCreate([
            'product_id' => $product->id,
            'quantity' => $stock,
            'weight' => 0,
        ]);

        return $product;
    }

    private function addLine(Order $order, Product $product, float $qty): OrderProduct
    {
        return OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'weight' => null,
            'product_weight' => null,
            'unit_price' => $product->price,
            'price' => $product->price * $qty,
            'status' => OrderProduct::$status['active'],
        ]);
    }

    /** @test */
    public function cancelled_credit_order_can_be_restored_reinstating_the_amount_owed(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        // Charge, then cancel — the reversal clears what the customer owed.
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, $admin->id);
        app(OrderStatusService::class)->transition($order->fresh(), Order::$status['cancelled'], $admin->id);
        $this->assertSame(Order::$status['cancelled'], $order->fresh()->status);
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);

        // Restore to delivered — a confirmed credit-term charge auto-parks it on credit.
        $restored = app(OrderStatusService::class)->restoreFromCancelled($order->fresh(), Order::$status['delivered'], $admin->id);

        $this->assertSame(Order::$status['credit'], $restored->status);
        // Customer owes the 30 again.
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
        // The reversal ledger entry is gone and the voided payment is confirmed again.
        $this->assertSame(0, CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_reversal')->count());
        $this->assertSame(1, OrderPayment::where('order_id', $order->id)
            ->where('payment_method', 'credit-term')
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->count());
    }

    /** @test */
    public function restoring_to_delivered_re_deducts_stock(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCodCustomer();
        $product = $this->makeQtyProduct(100);
        $order = $this->makeOrder($customer, [
            'payment_method' => 'cash',
            'status' => 'in_route',
            'driver_id' => null,
        ]);
        $this->addLine($order, $product, 4);

        // Move through cancellation from in_route: stock was deducted then restored.
        app(OrderStatusService::class)->transition($order->fresh(), Order::$status['cancelled'], $admin->id);
        $this->assertEqualsWithDelta(100, (float) $product->fresh()->stock->quantity, 0.001);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->where('movement_type', 'sales_deduction')->count());

        app(OrderStatusService::class)->restoreFromCancelled($order->fresh(), Order::$status['delivered'], $admin->id);

        // 4 units deducted again.
        $this->assertEqualsWithDelta(96, (float) $product->fresh()->stock->quantity, 0.001);
        $this->assertSame(1, StockMovement::where('order_id', $order->id)->where('movement_type', 'sales_deduction')->count());
    }

    /** @test */
    public function restoring_to_packing_does_not_deduct_stock(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCodCustomer();
        $product = $this->makeQtyProduct(100);
        $order = $this->makeOrder($customer, [
            'payment_method' => 'cash',
            'status' => 'packing',
        ]);
        $this->addLine($order, $product, 4);

        // Packing never deducted stock; cancelling leaves it untouched.
        app(OrderStatusService::class)->transition($order->fresh(), Order::$status['cancelled'], $admin->id);
        $this->assertEqualsWithDelta(100, (float) $product->fresh()->stock->quantity, 0.001);

        $restored = app(OrderStatusService::class)->restoreFromCancelled($order->fresh(), Order::$status['packing'], $admin->id);

        $this->assertSame(Order::$status['packing'], $restored->status);
        $this->assertEqualsWithDelta(100, (float) $product->fresh()->stock->quantity, 0.001);
        $this->assertSame(0, StockMovement::where('order_id', $order->id)->where('movement_type', 'sales_deduction')->count());
    }

    /** @test */
    public function next_statuses_offers_restore_targets_for_a_cancelled_order(): void
    {
        $customer = $this->makeCodCustomer();
        $order = $this->makeOrder($customer, ['payment_method' => 'cash', 'status' => 'cancelled']);

        $next = app(OrderStatusService::class)->nextStatuses($order->fresh());

        $this->assertContains(Order::$status['delivered'], $next);
        $this->assertContains(Order::$status['packing'], $next);
        $this->assertNotContains(Order::$status['cancelled'], $next);
    }

    /** @test */
    public function transition_from_cancelled_routes_through_restore(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCodCustomer();
        $order = $this->makeOrder($customer, ['payment_method' => 'cash', 'status' => 'cancelled']);

        $result = app(OrderStatusService::class)->transition($order->fresh(), Order::$status['packing'], $admin->id);

        $this->assertSame(Order::$status['packing'], $result->status);
    }

    /** @test */
    public function restoring_to_cancelled_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCodCustomer();
        $order = $this->makeOrder($customer, ['payment_method' => 'cash', 'status' => 'cancelled']);

        $this->expectException(\InvalidArgumentException::class);
        app(OrderStatusService::class)->restoreFromCancelled($order->fresh(), Order::$status['cancelled'], $admin->id);
    }

    /** @test */
    public function admin_can_restore_a_cancelled_order_over_http(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCodCustomer();
        $order = $this->makeOrder($customer, ['payment_method' => 'cash', 'status' => 'cancelled']);

        $response = $this->actingAs($admin, 'web_admin')
            ->post('/admin/order/update-status/' . $order->id, [
                'status' => Order::$status['delivered'],
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame(Order::$status['delivered'], $order->fresh()->status);
    }
}
