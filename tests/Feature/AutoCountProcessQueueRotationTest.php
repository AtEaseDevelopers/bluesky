<?php

namespace Tests\Feature;

use App\Order;
use App\Services\AutoCountApiService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Head-of-line regression: the process queue hands the plugin one order per
 * poll. An order now syncs straight to an Invoice / Cash Sale, but a document
 * can still be parked on AutoCount's approval gate, be a zero-total dead end, or
 * error before it reports back — in every case it stays 'pending_sync' with
 * api_invoice_id NULL, indistinguishable from a brand new order. With a plain
 * orderBy('id')->first() the oldest stuck order is returned on EVERY poll,
 * starving every newer web order.
 *
 * nextProcessOrder() must therefore rotate through the eligible set so no single
 * order can monopolise the head.
 */
class AutoCountProcessQueueRotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Array cache persists in-process across tests; clear the rotation
        // cursor so each test starts from a known position.
        Cache::flush();
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
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'sql_customer_code' => '300-' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    /**
     * A queued order that has not yet had its Invoice / Cash Sale written back:
     * still pending_sync, api_invoice_id NULL.
     */
    private function makePendingOrder(User $customer): Order
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
            'autocount_sync_status' => 'pending_sync',
            'api_invoice_id' => null,
            'invoice_number' => 'INV-' . rand(10000, 99999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /**
     * @test
     */
    public function process_queue_does_not_starve_newer_orders_behind_a_stuck_head(): void
    {
        $customer = $this->makeCustomer();
        $stuck = $this->makePendingOrder($customer);   // lower id — parked on approval / erroring
        $fresh = $this->makePendingOrder($customer);   // higher id — brand new web order

        $service = app(AutoCountApiService::class);

        $first = $service->nextProcessOrder();
        $second = $service->nextProcessOrder();

        $this->assertSame($stuck->id, $first['order']['id'], 'First poll should serve the oldest eligible order.');
        $this->assertSame(
            $fresh->id,
            $second['order']['id'],
            'Second poll must advance to the newer order, not re-serve the stuck head.'
        );
    }

    /**
     * @test
     */
    public function process_cursor_wraps_back_to_the_oldest_order(): void
    {
        $customer = $this->makeCustomer();
        $a = $this->makePendingOrder($customer);
        $b = $this->makePendingOrder($customer);

        $service = app(AutoCountApiService::class);

        $this->assertSame($a->id, $service->nextProcessOrder()['order']['id']);
        $this->assertSame($b->id, $service->nextProcessOrder()['order']['id']);
        $this->assertSame(
            $a->id,
            $service->nextProcessOrder()['order']['id'],
            'After the last eligible order the cursor must wrap around to the oldest.'
        );
    }

    /**
     * @test
     */
    public function a_single_eligible_order_is_served_on_every_poll(): void
    {
        $customer = $this->makeCustomer();
        $only = $this->makePendingOrder($customer);

        $service = app(AutoCountApiService::class);

        // A lone order (e.g. still awaiting Invoice approval) must keep being
        // offered so it eventually completes — rotation must not skip it.
        $this->assertSame($only->id, $service->nextProcessOrder()['order']['id']);
        $this->assertSame($only->id, $service->nextProcessOrder()['order']['id']);
    }
}
