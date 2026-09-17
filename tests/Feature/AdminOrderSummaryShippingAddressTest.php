<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin editing of the shipping address from the order summary page. Only the
 * orders.edit permission is required; the four shipping columns are updated in
 * place and reflected back on the summary.
 */
class AdminOrderSummaryShippingAddressTest extends TestCase
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
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'sql_customer_code' => '3000-T527',
            'invoice_price_permission' => 1,
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
            'total_price' => 0,
            'subtotal' => 0,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '10 Old Lane',
            'shipping_city' => 'Klang',
            'shipping_postcode' => '41000',
            'shipping_state' => 'Selangor',
        ], $attrs));
    }

    /** @test */
    public function admin_can_update_the_shipping_address(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.shipping-address', $order->id), [
                'shipping_address' => '88 New Tower, Shah Alam, 40000 Selangor',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('88 New Tower, Shah Alam, 40000 Selangor', $order->shipping_address);
    }

    /** @test */
    public function a_blank_shipping_address_is_stored_as_null(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.shipping-address', $order->id), [
                'shipping_address' => '   ',
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();

        $this->assertNull($order->shipping_address);
    }

    /** @test */
    public function an_overlong_shipping_address_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.shipping-address', $order->id), [
                'shipping_address' => str_repeat('a', 256),
            ])
            ->assertSessionHasErrors('shipping_address');

        $order->refresh();
        $this->assertSame('10 Old Lane', $order->shipping_address);
    }

    /** @test */
    public function admin_without_orders_edit_permission_is_forbidden(): void
    {
        $admin = $this->makeAdmin('viewer');
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.shipping-address', $order->id), [
                'shipping_address' => 'Hacked St',
            ])
            ->assertForbidden();

        $order->refresh();
        $this->assertSame('10 Old Lane', $order->shipping_address);
    }

    /** @test */
    public function summary_page_renders_the_editable_shipping_address_form(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee('id="shipping-address-form"', false)
            ->assertSee('name="shipping_address"', false)
            // the textarea itself is pre-filled with the current value
            ->assertSee('>10 Old Lane</textarea>', false);
    }
}
