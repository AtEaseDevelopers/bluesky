<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\Role;
use App\Services\RolePermissionService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A view-only admin (orders.view but not orders.edit) may open the order list
 * and order summary pages, but must NOT be shown status-change controls that
 * would 403 on click through AdminRoleCheck. This guards against the confusing
 * "You do not have permission to access this module." error a view-only admin
 * hit when clicking a status button that should never have been rendered.
 */
class AdminOrderStatusEditGatingTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);
    }

    private function viewOnlyAdmin(): Admin
    {
        $role = app(RolePermissionService::class)->createRole(
            ['name' => 'Orders Viewer ' . rand(1000, 9999), 'portal' => Role::PORTAL_ADMIN],
            ['orders.view']
        );

        return Admin::forceCreate([
            'name' => 'Jiawen',
            'username' => 'jiawen' . rand(1000, 9999),
            'email' => 'jiawen' . rand(1000, 9999) . '@example.com',
            'role' => $role->slug,
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
    public function view_only_admin_can_open_summary_but_sees_no_status_buttons(): void
    {
        $order = $this->makeOrder($this->makeCustomer());

        $this->actingAs($this->viewOnlyAdmin(), 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertDontSee(__('orders.status_actions'))
            ->assertDontSee('data-status="', false);
    }

    /** @test */
    public function edit_admin_sees_status_buttons_on_summary(): void
    {
        $order = $this->makeOrder($this->makeCustomer());

        $this->actingAs($this->superadmin(), 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee(__('orders.status_actions'))
            ->assertSee('data-status="', false);
    }

    /** @test */
    public function view_only_admin_can_open_index_but_sees_no_batch_status_button(): void
    {
        $this->makeOrder($this->makeCustomer());

        $this->actingAs($this->viewOnlyAdmin(), 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertDontSee('id="change-order-statuses"', false);
    }

    /** @test */
    public function edit_admin_sees_batch_status_button_on_index(): void
    {
        $this->makeOrder($this->makeCustomer());

        $this->actingAs($this->superadmin(), 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('id="change-order-statuses"', false);
    }
}
