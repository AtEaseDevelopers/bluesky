<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Walk-in orders must be clearly marked with a "Walk-in" badge (bracket) next
 * to the name on the admin order listing and order summary pages, be
 * filterable by order type, and show the walk-in phone on the summary.
 */
class AdminWalkInDisplayTest extends TestCase
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
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
            'user_id' => null,
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
        ], $attrs));
    }

    /** @test */
    public function listing_shows_walk_in_name_with_a_walk_in_badge(): void
    {
        $admin = $this->makeAdmin();
        $this->makeOrder([
            'order_type' => 'walk_in',
            'walk_in_name' => 'Ah Meng',
            'walk_in_phone' => '0123456789',
        ]);

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders', ['status' => Order::LIST_STATUS_ALL]))
            ->assertOk()
            ->assertSee('Ah Meng')
            ->assertSee(__('order.order_type.walk_in'));
    }

    /** @test */
    public function listing_can_filter_by_walk_in_order_type(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();

        $registered = $this->makeOrder([
            'user_id' => $customer->id,
            'order_type' => 'registered',
        ]);
        $walkIn = $this->makeOrder([
            'order_type' => 'walk_in',
            'walk_in_name' => 'Ah Meng',
        ]);

        // The customer name also appears in the filter dropdown, so assert on the
        // per-row checkbox id which is only rendered for orders in the table.
        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders', [
                'status' => Order::LIST_STATUS_ALL,
                'order_type' => 'walk_in',
            ]))
            ->assertOk()
            ->assertSee('Ah Meng')
            ->assertSee('id="order_' . $walkIn->id . '"', false)
            ->assertDontSee('id="order_' . $registered->id . '"', false);
    }

    /** @test */
    public function summary_shows_walk_in_badge_and_phone(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder([
            'order_type' => 'walk_in',
            'walk_in_name' => 'Ah Meng',
            'walk_in_phone' => '0123456789',
        ]);

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee('Ah Meng')
            ->assertSee(__('order.order_type.walk_in'))
            ->assertSee('0123456789');
    }
}
