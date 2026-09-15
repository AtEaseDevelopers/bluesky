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
 * The Record Payment panel on the admin order summary page is hidden once the
 * balance due is settled (<= 0). Recording a payment is still allowed by the
 * backend for over-collection, but there is nothing left to collect from the
 * summary UI, so the panel is not offered.
 */
class AdminOrderSummaryRecordPaymentPanelTest extends TestCase
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
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function record_payment_panel_shows_when_balance_is_outstanding(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());

        $this->assertGreaterThan(0, $order->balanceDue());

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee('id="split-payment-form"', false);
    }

    /** @test */
    public function record_payment_panel_is_hidden_when_balance_is_settled(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), [
            'paid_amount' => 30.00,
            'payment_status' => 'paid',
        ]);
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        $this->assertEquals(0.0, $order->fresh()->balanceDue());

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertDontSee('id="split-payment-form"', false);
    }

    /** @test */
    public function record_payment_panel_is_hidden_when_overpaid(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), [
            'paid_amount' => 40.00,
            'payment_status' => 'paid',
        ]);
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 40.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        $this->assertLessThanOrEqual(0.0, $order->fresh()->balanceDue());

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertDontSee('id="split-payment-form"', false);
    }
}
