<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\OrderPayment;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admins can record a payment on an order in ANY status (the previous
 * packing/in-route/delivered restriction and the cancelled-order block were
 * removed). The per-payment balance guard still applies, so a partial amount is
 * used here to exercise statuses that were formerly blocked.
 */
class AdminRecordPaymentAnyStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $role = 'superadmin'): Admin
    {
        return Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => $role,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'customer_type' => 'cod',
            'status' => 'active',
            'payment_method' => 'cod',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(User $customer, string $status): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => $status,
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    private function recordCash(Admin $admin, Order $order, float $amount)
    {
        return $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.store', $order->id), [
                'payments' => [
                    ['payment_method' => 'cash', 'amount' => $amount, 'notes' => 'test'],
                ],
            ]);
    }

    /** @test */
    public function admin_can_record_payment_on_a_completed_order(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'completed');

        $this->recordCash($admin, $order, 10.00)->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 10.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
        $this->assertEquals(10.00, (float) $order->fresh()->paid_amount);
    }

    /** @test */
    public function admin_can_record_payment_on_a_cancelled_order(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'cancelled');

        $this->recordCash($admin, $order, 10.00)->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 10.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
    }

    /** @test */
    public function admin_can_record_payment_on_a_pending_order(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'pending');

        $this->recordCash($admin, $order, 30.00)->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
    }

    /** @test */
    public function admin_can_record_payment_on_a_fully_paid_order(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'delivered');

        // Existing confirmed payment settles the full balance (balance due = 0).
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
        $order->update(['paid_amount' => 30.00, 'payment_status' => 'paid']);
        $this->assertEquals(0.0, $order->fresh()->balanceDue());

        // Balance is zero, but the admin can still record another payment.
        $this->recordCash($admin, $order, 10.00)->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 10.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
        $this->assertEquals(40.00, (float) $order->fresh()->paid_amount);
    }

    /** @test */
    public function admin_can_record_more_than_the_balance_due(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'delivered');

        // Balance due is 30, but admin records 50 (over-collection).
        $this->recordCash($admin, $order, 50.00)->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
        $this->assertEquals(50.00, (float) $order->fresh()->paid_amount);
    }
}
