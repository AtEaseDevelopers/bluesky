<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin order listing's customer filter must also offer every walk-in
 * customer name (orders with no registered user account), and filtering by one
 * of those names must narrow the listing to that walk-in customer's orders.
 */
class AdminOrderWalkInCustomerFilterTest extends TestCase
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

    private function makeCustomer(string $name): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
        ]);
    }

    private function makeRegisteredOrder(User $customer, string $shipping): Order
    {
        return Order::forceCreate([
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
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'billing_address' => $shipping,
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => $shipping,
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeWalkInOrder(string $name, string $billing): Order
    {
        return Order::forceCreate([
            'user_id' => null,
            'order_type' => Order::$order_types['walk_in'],
            'walk_in_name' => $name,
            'total_price' => 80,
            'subtotal' => 80,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'pickup',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'billing_address' => $billing,
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => $billing,
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    /** @test */
    public function customer_filter_lists_every_walk_in_customer_name(): void
    {
        $admin = $this->makeAdmin();
        $this->makeWalkInOrder('John Tan', '1 JOHN STREET');
        $this->makeWalkInOrder('Mary Lee', '2 MARY STREET');

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('walk_in:John Tan')
            ->assertSee('walk_in:Mary Lee');
    }

    /** @test */
    public function walk_in_name_appears_only_once_even_across_multiple_orders(): void
    {
        $admin = $this->makeAdmin();
        $this->makeWalkInOrder('John Tan', '1 JOHN STREET');
        $this->makeWalkInOrder('John Tan', '3 JOHN AVENUE');

        $html = $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'value="walk_in:John Tan"'));
    }

    /** @test */
    public function filtering_by_a_walk_in_name_narrows_the_listing(): void
    {
        $admin = $this->makeAdmin();
        $this->makeWalkInOrder('John Tan', '1 JOHN STREET');
        $this->makeWalkInOrder('Mary Lee', '2 MARY STREET');

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders', ['customer' => 'walk_in:John Tan']))
            ->assertOk()
            ->assertSee('1 JOHN STREET')
            ->assertDontSee('2 MARY STREET');
    }

    /** @test */
    public function filtering_by_a_registered_customer_also_matches_walk_in_orders_of_the_same_name(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('Acme Seafood');
        $this->makeRegisteredOrder($customer, 'REG ACME ADDR');
        // A walk-in order placed under the same name as the registered customer.
        $this->makeWalkInOrder('Acme Seafood', 'WALKIN ACME ADDR');
        // An unrelated walk-in order that must stay out of the results.
        $this->makeWalkInOrder('Other Guy', 'OTHER ADDR');

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders', ['customer' => $customer->id]))
            ->assertOk()
            ->assertSee('REG ACME ADDR')
            ->assertSee('WALKIN ACME ADDR')
            ->assertDontSee('OTHER ADDR');
    }
}
