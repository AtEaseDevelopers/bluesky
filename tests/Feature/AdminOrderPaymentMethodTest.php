<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOrderPaymentMethodTest extends TestCase
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

    private function makeCustomer(string $customerType = 'cod'): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'customer_type' => $customerType,
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(?User $customer, array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
            'user_id' => $customer?->id,
            'order_type' => 'registered',
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function admin_can_change_a_cod_order_to_in_store(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder($customer, ['payment_method' => 'cod']);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payment-method', $order->id), [
                'payment_method' => 'in-store',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'in-store',
        ]);
    }

    /** @test */
    public function admin_can_change_a_credit_order_to_term(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer, ['payment_method' => 'in-store']);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payment-method', $order->id), [
                'payment_method' => 'term',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'term',
        ]);
    }

    /** @test */
    public function a_method_not_allowed_for_the_customer_type_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder($customer, ['payment_method' => 'cod']);

        // 'term' is a credit-only method; a COD customer must not be able to use it.
        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payment-method', $order->id), [
                'payment_method' => 'term',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'cod',
        ]);
    }

    /** @test */
    public function payment_method_is_required(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder($customer, ['payment_method' => 'cod']);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payment-method', $order->id), [])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'cod',
        ]);
    }

    /** @test */
    public function payment_method_can_change_even_on_a_delivered_fully_paid_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder($customer, [
            'payment_method' => 'cod',
            'status' => 'delivered',
            'paid_amount' => 30.00,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payment-method', $order->id), [
                'payment_method' => 'in-store',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'in-store',
        ]);
    }

    /** @test */
    public function payment_method_can_change_even_on_a_cancelled_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder($customer, [
            'payment_method' => 'cod',
            'status' => 'cancelled',
        ]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payment-method', $order->id), [
                'payment_method' => 'in-store',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'in-store',
        ]);
    }

    /** @test */
    public function walk_in_order_can_switch_between_cod_and_in_store(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder(null, [
            'order_type' => 'walk_in',
            'payment_method' => 'cod',
            'walk_in_name' => 'Cash Buyer',
        ]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payment-method', $order->id), [
                'payment_method' => 'in-store',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'in-store',
        ]);
    }

    /** @test */
    public function admin_without_orders_edit_permission_is_forbidden(): void
    {
        $admin = $this->makeAdmin('viewer');
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder($customer, ['payment_method' => 'cod']);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payment-method', $order->id), [
                'payment_method' => 'in-store',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'cod',
        ]);
    }
}
