<?php

namespace Tests\Feature;

use App\AutoCountSyncLog;
use App\Order;
use App\Services\AutoCountApiService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * AutoCount assigns Delivery Order numbers from its own auto running-number,
 * which restarts at 000001 whenever the book is reset during testing. A reset
 * lets a fresh sync mint a DO number that another order already owns, and the
 * blind write-back used to overwrite it — silently pointing two orders at the
 * same DO and breaking the later cash-sale transfer with
 * "has been transferred to other document".
 */
class AutoCountDocumentCollisionTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(User $customer, array $overrides = []): Order
    {
        return Order::forceCreate(array_merge([
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
            'autocount_sync_status' => 'pending_sync',
            'api_do_id' => null,
            'invoice_number' => 'INV-' . rand(10000, 99999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $overrides));
    }

    /**
     * A DO write-back must never overwrite a DO number already owned by another
     * order. The write-back is rejected, the victim order is left untouched
     * except for a visible sync_error, and the original owner is unchanged.
     *
     * @test
     */
    public function do_writeback_does_not_overwrite_number_owned_by_another_order(): void
    {
        $customer = $this->makeCustomer('cod');
        $owner = $this->makeOrder($customer, [
            'api_do_id' => 'DO-000001',
            'autocount_sync_status' => 'do_created',
        ]);
        $victim = $this->makeOrder($customer, [
            'api_do_id' => null,
            'autocount_sync_status' => 'pending_sync',
        ]);

        app(AutoCountApiService::class)->applyDocumentUpdate([
            'id' => $victim->id,
            'type' => 'DO',
            'number' => 'DO-000001',
        ]);

        $victim->refresh();
        $owner->refresh();

        // Victim not corrupted: number rejected, order flagged for attention.
        $this->assertNull($victim->api_do_id);
        $this->assertSame('sync_error', $victim->autocount_sync_status);

        // Original owner untouched.
        $this->assertSame('DO-000001', $owner->api_do_id);
        $this->assertSame('do_created', $owner->autocount_sync_status);

        // Collision is recorded so it is diagnosable, not silent.
        $log = AutoCountSyncLog::where('order_id', $victim->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsStringIgnoringCase('collision', (string) $log->error_message);
        $this->assertStringContainsString('DO-000001', (string) $log->error_message);
    }

    /**
     * Re-delivering the SAME DO number to the SAME order is idempotent, not a
     * collision — the plugin may resend a write-back.
     *
     * @test
     */
    public function do_writeback_is_idempotent_for_the_same_order(): void
    {
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder($customer, [
            'api_do_id' => 'DO-000001',
            'autocount_sync_status' => 'do_created',
        ]);

        app(AutoCountApiService::class)->applyDocumentUpdate([
            'id' => $order->id,
            'type' => 'DO',
            'number' => 'DO-000001',
        ]);

        $order->refresh();

        $this->assertSame('DO-000001', $order->api_do_id);
        $this->assertSame('do_created', $order->autocount_sync_status);
    }

    /**
     * The same guard protects the invoice slot (CS/INV) from cross-order reuse.
     *
     * @test
     */
    public function cash_sale_writeback_does_not_overwrite_invoice_number_owned_by_another_order(): void
    {
        $customer = $this->makeCustomer('cod');
        $owner = $this->makeOrder($customer, [
            'api_do_id' => 'DO-000002',
            'api_invoice_id' => 'CS-000009',
            'autocount_sync_status' => 'paid_synced',
        ]);
        $victim = $this->makeOrder($customer, [
            'api_do_id' => 'DO-000003',
            'autocount_sync_status' => 'do_created',
        ]);

        app(AutoCountApiService::class)->applyDocumentUpdate([
            'id' => $victim->id,
            'type' => 'CS',
            'number' => 'CS-000009',
        ]);

        $victim->refresh();

        $this->assertNull($victim->api_invoice_id);
        $this->assertSame('sync_error', $victim->autocount_sync_status);
        $this->assertSame('CS-000009', $owner->fresh()->api_invoice_id);
    }

    /**
     * A plugin error saying the source DO was already transferred is the
     * downstream symptom of a DO-number collision. It must be recorded with a
     * clear diagnostic (while preserving the raw message), and must leave the
     * order in sync_error so /process stops re-serving it.
     *
     * @test
     */
    public function already_transferred_error_is_recorded_with_a_clear_diagnostic(): void
    {
        $customer = $this->makeCustomer('cod');
        $order = $this->makeOrder($customer, [
            'api_do_id' => 'DO-000001',
            'autocount_sync_status' => 'do_created',
        ]);

        $raw = 'NEW Cash Sales LOG:The from document DO-000001 you want to '
            . 'full transfer has been transferred to other document.';

        app(AutoCountApiService::class)->logError([
            'message' => $raw,
            'model' => ['order' => ['id' => $order->id]],
        ]);

        $order->refresh();

        $this->assertSame('sync_error', $order->autocount_sync_status);

        $log = AutoCountSyncLog::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($log);
        // Clear diagnostic added...
        $this->assertStringContainsStringIgnoringCase('collision', (string) $log->error_message);
        // ...without losing the raw plugin message.
        $this->assertStringContainsString('has been transferred to other document', (string) $log->error_message);
    }

    /**
     * Ordinary plugin errors are still recorded verbatim as sync_error, with no
     * spurious collision wording.
     *
     * @test
     */
    public function unrelated_plugin_error_is_recorded_verbatim(): void
    {
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer, [
            'api_do_id' => 'DO-000005',
            'autocount_sync_status' => 'do_created',
        ]);

        app(AutoCountApiService::class)->logError([
            'message' => 'DO not found in AutoCount, save aborted.',
            'model' => ['order' => ['id' => $order->id]],
        ]);

        $order->refresh();
        $log = AutoCountSyncLog::where('order_id', $order->id)->latest('id')->first();

        $this->assertSame('sync_error', $order->autocount_sync_status);
        $this->assertSame('DO not found in AutoCount, save aborted.', (string) $log->error_message);
    }
}
