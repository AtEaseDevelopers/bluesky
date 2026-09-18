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
 * Head-of-line regression, mirror of AutoCountPendingQueueRotationTest but for
 * the credit payment-sync queue. A credit order lands at 'synced' and is offered
 * on GET /api/order/paid until the plugin confirms the invoice was knocked off in
 * AutoCount. If the invoice is never paid off, that write-back never comes and the
 * order stays 'synced' forever. With a plain orderBy('id')->first() the oldest
 * unpaid credit order is returned on EVERY poll, starving every other credit order
 * of its own payment write-back (observed in prod: order #27 served 544 times, 0
 * others).
 *
 * nextPaidOrder() must rotate through the synced-credit set so no single unpaid
 * invoice can monopolise the head.
 */
class AutoCountPaidQueueRotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Array cache persists in-process across tests; clear the rotation
        // cursor so each test starts from a known position.
        Cache::flush();
    }

    private function makeCreditCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'status' => 'active',
            'payment_method' => 'credit',
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
     * A synced credit order awaiting its payment write-back: invoice created in
     * AutoCount but not yet knocked off, so it sits at 'synced' with an invoice id.
     */
    private function makeSyncedCreditOrder(User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 20.00,
            'subtotal' => 20.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => Order::$status['delivered'],
            'fulfillment_type' => 'delivery',
            'payment_method' => 'credit',
            'payment_status' => Order::$payment_status['pending'],
            'autocount_sync_status' => 'synced',
            'api_do_id' => 'DO-' . rand(10000, 99999),
            'api_invoice_id' => 'INV-' . rand(10000, 99999),
            'invoice_number' => 'INV-' . rand(10000, 99999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /**
     * @test
     */
    public function paid_queue_does_not_starve_other_orders_behind_a_stuck_head(): void
    {
        $customer = $this->makeCreditCustomer();
        $stuck = $this->makeSyncedCreditOrder($customer);   // lower id — invoice never knocked off
        $fresh = $this->makeSyncedCreditOrder($customer);   // higher id — awaiting its own write-back

        $service = app(AutoCountApiService::class);

        $first = $service->nextPaidOrder();
        $second = $service->nextPaidOrder();

        $this->assertSame($stuck->id, $first['order']['id'], 'First poll should serve the oldest synced credit order.');
        $this->assertSame(
            $fresh->id,
            $second['order']['id'],
            'Second poll must advance to the next order, not re-serve the stuck head.'
        );
    }

    /**
     * @test
     */
    public function paid_cursor_wraps_back_to_the_oldest_order(): void
    {
        $customer = $this->makeCreditCustomer();
        $a = $this->makeSyncedCreditOrder($customer);
        $b = $this->makeSyncedCreditOrder($customer);

        $service = app(AutoCountApiService::class);

        $this->assertSame($a->id, $service->nextPaidOrder()['order']['id']);
        $this->assertSame($b->id, $service->nextPaidOrder()['order']['id']);
        $this->assertSame(
            $a->id,
            $service->nextPaidOrder()['order']['id'],
            'After the last synced credit order the cursor must wrap around to the oldest.'
        );
    }

    /**
     * @test
     */
    public function a_single_synced_credit_order_is_served_on_every_poll(): void
    {
        $customer = $this->makeCreditCustomer();
        $only = $this->makeSyncedCreditOrder($customer);

        $service = app(AutoCountApiService::class);

        // A lone unpaid credit order must keep being offered so its payment
        // write-back is eventually received — rotation must not skip it.
        $this->assertSame($only->id, $service->nextPaidOrder()['order']['id']);
        $this->assertSame($only->id, $service->nextPaidOrder()['order']['id']);
    }
}
