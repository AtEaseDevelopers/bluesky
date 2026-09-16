<?php

namespace Tests\Feature;

use App\Admin;
use App\Driver;
use App\Order;
use App\OrderPayment;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A double-submitted "Record Payment" form (double-click / accidental re-post)
 * must record the payment only once. Each form render carries a one-time
 * _submit_token; the second request reusing that token is ignored.
 */
class PaymentDoubleSubmitTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $admin = Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);

        return $admin;
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

    private function makeOrder(User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 150.00,
            'subtotal' => 150.00,
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
        ]);
    }

    /** @test */
    public function reusing_the_same_submit_token_records_the_payment_once(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());

        $token = 'tok-' . uniqid();
        $payload = [
            '_submit_token' => $token,
            'payments' => [
                ['payment_method' => 'cash', 'amount' => '150.00'],
            ],
        ];

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.store', $order->id), $payload)
            ->assertRedirect();

        // Second, identical post (the accidental double-submit).
        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.store', $order->id), $payload)
            ->assertRedirect();

        $this->assertSame(1, OrderPayment::where('order_id', $order->id)->count());
        $this->assertEquals(150.00, (float) $order->fresh()->paid_amount);
    }

    /** @test */
    public function distinct_submit_tokens_still_record_each_payment(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());

        foreach (['a', 'b'] as $t) {
            $this->actingAs($admin, 'web_admin')
                ->post(route('admin.orders.payments.store', $order->id), [
                    '_submit_token' => 'tok-' . $t,
                    'payments' => [
                        ['payment_method' => 'cash', 'amount' => '50.00'],
                    ],
                ])->assertRedirect();
        }

        $this->assertSame(2, OrderPayment::where('order_id', $order->id)->count());
        $this->assertEquals(100.00, (float) $order->fresh()->paid_amount);
    }

    /** @test */
    public function the_record_payment_form_embeds_a_submit_token(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee('name="_submit_token"', false);
    }

    /** @test */
    public function driver_double_submit_records_the_payment_once(): void
    {
        $driver = Driver::create([
            'name' => 'Ali Driver',
            'phone' => '0123456789',
            'lorry_number' => 'LRY-1',
            'username' => 'driver' . rand(1000, 9999),
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer();
        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 150.00,
            'subtotal' => 150.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'in_route',
            'fulfillment_type' => Order::$fulfillment_types['delivery'],
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'driver_id' => $driver->id,
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);

        $token = 'drv-' . uniqid();
        $payload = ['_submit_token' => $token, 'payment_method' => 'cash', 'paid_amount' => 150.00];

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.record-payment', $order->id), $payload)
            ->assertRedirect();
        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.record-payment', $order->id), $payload)
            ->assertRedirect();

        $this->assertSame(1, OrderPayment::where('order_id', $order->id)->count());
    }
}
