<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCustomerBulkDeleteTest extends TestCase
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
            'name' => 'Customer ' . rand(1000, 999999),
            'email' => 'cust' . rand(1000, 999999) . '@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function makeOrder(User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 20.00,
            'subtotal' => 20.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 20.00,
            'status' => Order::$status['completed'],
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => Order::$payment_status['paid'],
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /** @test */
    public function it_bulk_deletes_customers_without_orders(): void
    {
        $admin = $this->makeAdmin();
        $a = $this->makeCustomer();
        $b = $this->makeCustomer();

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.bulk-delete'), [
                'customer_ids' => [$a->id, $b->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $a->id]);
        $this->assertDatabaseMissing('users', ['id' => $b->id]);
    }

    /** @test */
    public function it_skips_customers_with_orders_and_keeps_them(): void
    {
        $admin = $this->makeAdmin();
        $withOrder = $this->makeCustomer();
        $this->makeOrder($withOrder);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.bulk-delete'), [
                'customer_ids' => [$withOrder->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // Related relationship (orders) must block deletion.
        $this->assertDatabaseHas('users', ['id' => $withOrder->id]);
    }

    /** @test */
    public function it_deletes_deletable_and_skips_blocked_in_mixed_selection(): void
    {
        $admin = $this->makeAdmin();
        $deletable = $this->makeCustomer();
        $blocked = $this->makeCustomer();
        $this->makeOrder($blocked);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.bulk-delete'), [
                'customer_ids' => [$deletable->id, $blocked->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $deletable->id]);
        $this->assertDatabaseHas('users', ['id' => $blocked->id]);
    }

    /** @test */
    public function it_validates_that_at_least_one_customer_is_selected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.bulk-delete'), [
                'customer_ids' => [],
            ])
            ->assertSessionHasErrors('customer_ids');
    }
}
