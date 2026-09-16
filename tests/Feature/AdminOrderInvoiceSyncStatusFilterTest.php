<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin order listing must offer an "Invoice Sync Status" filter that lets
 * an admin narrow the listing to orders in a given AutoCount sync state
 * (autocount_sync_status).
 */
class AdminOrderInvoiceSyncStatusFilterTest extends TestCase
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

    private function makeOrder(string $syncStatus, string $address): Order
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
            'billing_address' => $address,
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => $address,
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    /** @test */
    public function listing_renders_an_invoice_sync_status_filter_dropdown(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('name="autocount_sync_status"', false);
    }

    /** @test */
    public function filtering_by_sync_status_narrows_the_listing(): void
    {
        $admin = $this->makeAdmin();
        $this->makeOrder('synced', 'SYNCED ADDR');
        $this->makeOrder('sync_error', 'ERROR ADDR');

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders', ['autocount_sync_status' => 'synced']))
            ->assertOk()
            ->assertSee('SYNCED ADDR')
            ->assertDontSee('ERROR ADDR');
    }

    /** @test */
    public function no_sync_status_filter_shows_all_orders(): void
    {
        $admin = $this->makeAdmin();
        $this->makeOrder('synced', 'SYNCED ADDR');
        $this->makeOrder('sync_error', 'ERROR ADDR');

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('SYNCED ADDR')
            ->assertSee('ERROR ADDR');
    }
}
