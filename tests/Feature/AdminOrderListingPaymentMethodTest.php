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
 * The admin order listing's "Payment Method" column must reflect the actual
 * recorded (confirmed) payments — not the customer's preferred/checkout method.
 * When nothing has been recorded yet, it shows a dash.
 */
class AdminOrderListingPaymentMethodTest extends TestCase
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

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
        ]);
    }

    private function makeOrder(User $customer, array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 80,
            'subtotal' => 80,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash', // preferred/checkout method
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function listing_shows_recorded_payment_breakdown_instead_of_preferred_method(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        // Preferred method is "cash", but the money actually came in via
        // bank transfer + cash. The listing must show what was recorded.
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'bank-transfer',
            'amount' => 50.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        // Method only — no amounts in the listing column.
        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Bank Transfer + Cash')
            ->assertDontSee('RM 50.00')
            ->assertDontSee('RM 30.00');
    }

    /** @test */
    public function listing_ignores_pending_and_rejected_payments(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'bank-transfer',
            'amount' => 50.00,
            'status' => OrderPayment::STATUS_PENDING,
        ]);
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'qr',
            'amount' => 20.00,
            'status' => OrderPayment::STATUS_REJECTED,
        ]);

        // Only confirmed payments count towards the recorded-methods label;
        // a pending/rejected-only order shows a dash.
        $this->assertSame('-', $order->fresh()->recordedPaymentMethodsLabel());

        // And the listing renders without error for such an order.
        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk();
    }
}
