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
 * The AutoCount Delivery Order must carry its own "DO-YYYYMM-####" number, paired
 * to the invoice number, rather than reusing the "INV-" number. The plugin reads
 * order.do_no off the sync payload to stamp the DO's DocNo, so the payload has to
 * expose a DO-prefixed number for every pending order.
 */
class AutoCountDeliveryOrderNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function makePendingOrder(User $customer, string $invoiceNumber): Order
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
            'api_do_id' => null,
            'invoice_number' => $invoiceNumber,
            'do_no' => null,
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /**
     * @test
     */
    public function pending_payload_exposes_a_do_number_paired_to_the_invoice(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makePendingOrder($customer, 'INV-202609-00400');

        $payload = app(AutoCountApiService::class)->nextPendingOrder();

        $this->assertSame($order->id, $payload['order']['id']);
        $this->assertSame(
            'DO-202609-00400',
            $payload['order']['do_no'],
            'DO number must mirror the invoice sequence but keep the DO- prefix.'
        );
        $this->assertSame('INV-202609-00400', $payload['order']['invoice_number']);
    }

    /**
     * @test
     */
    public function pending_payload_always_includes_a_do_prefixed_number(): void
    {
        $customer = $this->makeCustomer();
        // Non-standard invoice number (no YYYYMM-#### sequence to pair from):
        // a fresh DO- number is still minted and surfaced on the payload.
        $order = $this->makePendingOrder($customer, 'INV-LEGACY-1');

        $payload = app(AutoCountApiService::class)->nextPendingOrder();

        $this->assertSame($order->id, $payload['order']['id']);
        $this->assertMatchesRegularExpression(
            '/^DO-\d{6}-\d+$/',
            (string) $payload['order']['do_no'],
            'Every pending order must sync with a standard DO- number.'
        );
    }
}
