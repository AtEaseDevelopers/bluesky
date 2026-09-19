<?php

namespace Tests\Feature;

use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QueueCreditSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $type = 'credit'): User
    {
        return User::forceCreate([
            'name' => 'Cust ' . rand(1000, 9999),
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => $type,
            'customer_type' => $type,
            'status' => 'active',
            'payment_method' => 'term',
            'login_code' => 'code' . rand(1000, 9999),
            'sql_customer_code' => '300-' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

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
            'status' => Order::$status['delivered'],
            'fulfillment_type' => 'delivery',
            'payment_method' => 'credit-term',
            'payment_status' => Order::$payment_status['paid'],
            'autocount_sync_status' => 'pending',
            'autocount_synced_at' => null,
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $overrides));
    }

    /** @test */
    public function it_queues_delivered_and_completed_credit_orders(): void
    {
        $customer = $this->makeCustomer('credit');
        $delivered = $this->makeOrder($customer, ['api_do_id' => null, 'api_invoice_id' => null]);
        $completed = $this->makeOrder($customer, [
            'status' => Order::$status['completed'],
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:queue-credit-sync')->assertExitCode(0);

        $this->assertSame('pending_sync', $delivered->fresh()->autocount_sync_status);
        $this->assertNull($delivered->fresh()->autocount_synced_at);
        $this->assertSame('pending_sync', $completed->fresh()->autocount_sync_status);
    }

    /** @test */
    public function dry_run_changes_nothing(): void
    {
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer, ['api_do_id' => null, 'api_invoice_id' => null]);

        $this->artisan('orders:queue-credit-sync --dry-run')->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_ignores_non_credit_customers(): void
    {
        $cod = $this->makeCustomer('cod');
        $order = $this->makeOrder($cod, [
            'payment_method' => 'cash',
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:queue-credit-sync')->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_ignores_orders_that_are_not_fulfilled(): void
    {
        $customer = $this->makeCustomer('credit');
        $pending = $this->makeOrder($customer, [
            'status' => Order::$status['pending'],
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:queue-credit-sync')->assertExitCode(0);

        $this->assertSame('pending', $pending->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_skips_orders_with_existing_document_refs_without_force(): void
    {
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer, [
            'api_do_id' => 'DO-0001',
            'api_invoice_id' => 'INV-0001',
        ]);

        $this->artisan('orders:queue-credit-sync')->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_skips_synced_and_paid_synced_orders_without_force(): void
    {
        $customer = $this->makeCustomer('credit');
        $synced = $this->makeOrder($customer, [
            'autocount_sync_status' => 'synced',
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);
        $paidSynced = $this->makeOrder($customer, [
            'autocount_sync_status' => 'paid_synced',
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:queue-credit-sync')->assertExitCode(0);

        $this->assertSame('synced', $synced->fresh()->autocount_sync_status);
        $this->assertSame('paid_synced', $paidSynced->fresh()->autocount_sync_status);
    }

    /** @test */
    public function force_requeues_orders_with_document_refs_and_clears_them(): void
    {
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer, [
            'autocount_sync_status' => 'paid_synced',
            'api_do_id' => 'DO-0001',
            'api_invoice_id' => 'INV-0001',
        ]);

        $this->artisan('orders:queue-credit-sync --force')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending_sync', $order->autocount_sync_status);
        $this->assertNull($order->api_do_id);
        $this->assertNull($order->api_invoice_id);
    }

    /** @test */
    public function it_can_restrict_to_specific_ids(): void
    {
        $customer = $this->makeCustomer('credit');
        $target = $this->makeOrder($customer, ['api_do_id' => null, 'api_invoice_id' => null]);
        $other = $this->makeOrder($customer, ['api_do_id' => null, 'api_invoice_id' => null]);

        $this->artisan('orders:queue-credit-sync --id=' . $target->id)->assertExitCode(0);

        $this->assertSame('pending_sync', $target->fresh()->autocount_sync_status);
        $this->assertSame('pending', $other->fresh()->autocount_sync_status);
    }
}
