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
 * Credit orders terminate at 'synced'. Once the invoice is created in AutoCount
 * the order carries an outstanding AR balance settled on credit terms — that
 * settlement is not tracked back into the OMS, so there is nothing left to sync.
 *
 * The /paid queue must therefore NOT re-offer a synced credit order. Previously
 * nextPaidOrder() handed every synced credit order to the plugin on every poll,
 * which — with no write-back ever arriving — looped forever (observed in prod:
 * order #27 served 544 times, then 75 credit orders churning the sync log).
 */
class AutoCountCreditPaidQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
    public function a_synced_credit_order_is_not_offered_for_payment_sync(): void
    {
        $customer = $this->makeCreditCustomer();
        $this->makeSyncedCreditOrder($customer);

        $service = app(AutoCountApiService::class);

        $this->assertNull(
            $service->nextPaidOrder(),
            'A synced credit order is terminal and must not be re-offered on the /paid queue.'
        );
    }

    /**
     * @test
     */
    public function no_synced_credit_order_is_ever_returned_even_with_a_backlog(): void
    {
        $customer = $this->makeCreditCustomer();
        $this->makeSyncedCreditOrder($customer);
        $this->makeSyncedCreditOrder($customer);
        $this->makeSyncedCreditOrder($customer);

        $service = app(AutoCountApiService::class);

        // Repeated polls must stay empty — no rotation, no head-of-line churn.
        $this->assertNull($service->nextPaidOrder());
        $this->assertNull($service->nextPaidOrder());
        $this->assertNull($service->nextPaidOrder());
    }
}
