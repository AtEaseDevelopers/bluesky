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
 * Regression: the queuing gate (AutoCountSyncService::syncIfEligible) marks a
 * credit order pending_sync the moment it is DELIVERED — not paid, not
 * completed — matching Order::canSyncToAutoCount(). The poll query
 * (AutoCountApiService::baseOrderQuery) must recognise the same orders, or a
 * delivered credit order is queued but never handed to the plugin and sits in
 * pending_sync forever. A COD order, by contrast, still requires completed +
 * paid before it may surface.
 */
class AutoCountPollEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeCustomer(string $type): User
    {
        return User::forceCreate([
            'name' => ucfirst($type) . ' Buyer',
            'email' => 'cust' . rand(100000, 999999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => $type === 'credit' ? 'credit' : 'cod',
            'customer_type' => $type,
            'status' => 'active',
            'payment_method' => $type === 'credit' ? 'term' : 'cash',
            'login_code' => 'code' . rand(100000, 999999),
            'sql_customer_code' => '300-' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(User $customer, string $status, string $paymentStatus): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 20.00,
            'subtotal' => 20.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => $paymentStatus === Order::$payment_status['paid'] ? 20.00 : 0,
            'status' => $status,
            'fulfillment_type' => 'delivery',
            'payment_method' => $customer->customer_type === 'credit' ? 'credit-term' : 'cash',
            'payment_status' => $paymentStatus,
            'autocount_sync_status' => 'pending_sync',
            'api_do_id' => null,
            'invoice_number' => 'INV-' . rand(100000, 999999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /**
     * @test
     */
    public function a_delivered_unpaid_credit_order_is_handed_to_the_plugin(): void
    {
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder(
            $customer,
            Order::$status['delivered'],
            Order::$payment_status['unpaid']
        );

        $payload = app(AutoCountApiService::class)->nextProcessOrder();

        $this->assertNotNull(
            $payload,
            'A delivered credit order queued to pending_sync must surface to the poll, not stall.'
        );
        $this->assertSame($order->id, $payload['order']['id']);
    }

    /**
     * @test
     */
    public function a_delivered_unpaid_cod_order_is_not_handed_to_the_plugin(): void
    {
        $customer = $this->makeCustomer('cod');
        $this->makeOrder(
            $customer,
            Order::$status['delivered'],
            Order::$payment_status['unpaid']
        );

        $this->assertNull(
            app(AutoCountApiService::class)->nextProcessOrder(),
            'A COD order still needs completed + paid before it may sync.'
        );
    }

    /**
     * @test
     */
    public function a_completed_paid_cod_order_is_handed_to_the_plugin(): void
    {
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder(
            $customer,
            Order::$status['completed'],
            Order::$payment_status['paid']
        );

        $payload = app(AutoCountApiService::class)->nextProcessOrder();

        $this->assertNotNull($payload);
        $this->assertSame($order->id, $payload['order']['id']);
    }
}
