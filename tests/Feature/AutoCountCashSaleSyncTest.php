<?php

namespace Tests\Feature;

use App\Order;
use App\Services\AutoCountApiService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutoCountCashSaleSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $customerType = 'cod'): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => $customerType,
            'customer_type' => $customerType,
            'status' => 'active',
            'payment_method' => $customerType === 'credit' ? 'term' : 'cash',
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

    private function makeSyncedOrder(User $customer): Order
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
            'payment_method' => $customer->customer_type === 'credit' ? 'credit-term' : 'cash',
            'payment_status' => Order::$payment_status['paid'],
            'autocount_sync_status' => 'do_created',
            'api_do_id' => 'DO-0001',
            'invoice_number' => 'INV-0001',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /**
     * H1: A Cash Sale is settled the moment it is created in AutoCount, so a
     * COD/walk-in order syncing back as CS must land on 'paid_synced' — it will
     * never be picked up by the paid-sync endpoint (credit only).
     *
     * @test
     */
    public function cash_sale_writeback_marks_order_paid_synced(): void
    {
        $customer = $this->makeCustomer('cod');
        $order = $this->makeSyncedOrder($customer);

        app(AutoCountApiService::class)->applyDocumentUpdate([
            'id' => $order->id,
            'type' => 'CS',
            'number' => 'CS-0001',
        ]);

        $order->refresh();

        $this->assertSame('CS-0001', $order->api_invoice_id);
        $this->assertSame('paid_synced', $order->autocount_sync_status);
        $this->assertNotNull($order->autocount_synced_at);
    }

    /**
     * H1: An Invoice is an outstanding AR document, so a credit order syncing
     * back as INV must stay 'synced' and wait for the paid-sync endpoint to
     * confirm the payment knock-off.
     *
     * @test
     */
    public function invoice_writeback_marks_order_synced_awaiting_payment(): void
    {
        $customer = $this->makeCustomer('credit');
        $order = $this->makeSyncedOrder($customer);

        app(AutoCountApiService::class)->applyDocumentUpdate([
            'id' => $order->id,
            'type' => 'INV',
            'number' => 'INV-0001',
        ]);

        $order->refresh();

        $this->assertSame('INV-0001', $order->api_invoice_id);
        $this->assertSame('synced', $order->autocount_sync_status);
    }

    /**
     * H1 regression: a COD cash-sale order is settled on creation and must not
     * be offered to the paid-sync endpoint (which is credit-only). If it were,
     * it would loop forever waiting for a payment knock-off that never comes.
     *
     * @test
     */
    public function cash_sale_order_is_not_offered_to_paid_sync(): void
    {
        $customer = $this->makeCustomer('cod');
        $order = $this->makeSyncedOrder($customer);

        app(AutoCountApiService::class)->applyDocumentUpdate([
            'id' => $order->id,
            'type' => 'CS',
            'number' => 'CS-0001',
        ]);

        $this->assertNull(app(AutoCountApiService::class)->nextPaidOrder());
    }

    /**
     * H3 contract: when the plugin fails to build a document it posts the error
     * to /update-log instead of crashing. The server must record 'sync_error'
     * so the order is visibly stuck rather than silently lost.
     *
     * @test
     */
    public function plugin_error_log_marks_order_sync_error(): void
    {
        $customer = $this->makeCustomer('credit');
        $order = $this->makeSyncedOrder($customer);

        app(AutoCountApiService::class)->logError([
            'message' => 'DO not found in AutoCount, save aborted.',
            'model' => ['order' => ['id' => $order->id]],
        ]);

        $order->refresh();

        $this->assertSame('sync_error', $order->autocount_sync_status);
    }
}
