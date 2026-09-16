<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * When an order's AutoCount invoice sync is completed the admin order listing
 * surfaces the AutoCount DO and INV document numbers beneath the sync status
 * badge. Orders that have not completed sync must not show those numbers even
 * if the reference columns happen to be populated.
 */
class AdminOrderListingSyncedDocNumbersTest extends TestCase
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

    private function makeOrder(string $syncStatus, string $doNo, string $invNo): Order
    {
        return Order::forceCreate([
            'user_id' => null,
            'order_type' => Order::$order_types['walk_in'],
            'walk_in_name' => 'Walk In ' . rand(1000, 9999),
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
            'autocount_sync_status' => $syncStatus,
            'api_do_id' => $doNo,
            'api_invoice_id' => $invNo,
            'billing_address' => 'ADDR',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => 'ADDR',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    /** @test */
    public function completed_sync_shows_do_and_inv_numbers(): void
    {
        $admin = $this->makeAdmin();
        $this->makeOrder('synced', 'DO-990001', 'INV-990001');

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('DO-990001')
            ->assertSee('INV-990001');
    }

    /** @test */
    public function paid_synced_also_shows_do_and_inv_numbers(): void
    {
        $admin = $this->makeAdmin();
        $this->makeOrder('paid_synced', 'DO-990002', 'INV-990002');

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('DO-990002')
            ->assertSee('INV-990002');
    }

    /** @test */
    public function pending_sync_hides_do_and_inv_numbers(): void
    {
        $admin = $this->makeAdmin();
        $this->makeOrder('pending', 'DO-990003', 'INV-990003');

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertDontSee('DO-990003')
            ->assertDontSee('INV-990003');
    }
}
