<?php

namespace Tests\Feature;

use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RequeueDeliveredSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $name): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
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
            'autocount_sync_status' => 'synced',
            'autocount_synced_at' => now(),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $overrides));
    }

    /** @test */
    public function it_requeues_delivered_orders_for_named_customers(): void
    {
        $customer = $this->makeCustomer('Golden Chicken');
        $order = $this->makeOrder($customer, [
            'autocount_sync_status' => 'synced',
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:requeue-delivered-sync --name="Golden Chicken"')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending_sync', $order->autocount_sync_status);
        $this->assertNull($order->autocount_synced_at);
    }

    /** @test */
    public function customer_name_match_is_case_insensitive(): void
    {
        $customer = $this->makeCustomer('Golden Chicken');
        $order = $this->makeOrder($customer, ['api_do_id' => null, 'api_invoice_id' => null]);

        $this->artisan('orders:requeue-delivered-sync --name="golden chicken"')
            ->assertExitCode(0);

        $this->assertSame('pending_sync', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function dry_run_changes_nothing(): void
    {
        $customer = $this->makeCustomer('Golden Chicken');
        $order = $this->makeOrder($customer, ['api_do_id' => null, 'api_invoice_id' => null]);

        $this->artisan('orders:requeue-delivered-sync --name="Golden Chicken" --dry-run')
            ->assertExitCode(0);

        $this->assertSame('synced', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_ignores_orders_that_are_not_delivered(): void
    {
        $customer = $this->makeCustomer('Golden Chicken');
        $completed = $this->makeOrder($customer, [
            'status' => Order::$status['completed'],
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:requeue-delivered-sync --name="Golden Chicken"')
            ->assertExitCode(0);

        $this->assertSame('synced', $completed->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_only_touches_the_named_customers(): void
    {
        $target = $this->makeCustomer('Golden Chicken');
        $other = $this->makeCustomer('Sin Kee');
        $targetOrder = $this->makeOrder($target, ['api_do_id' => null, 'api_invoice_id' => null]);
        $otherOrder = $this->makeOrder($other, ['api_do_id' => null, 'api_invoice_id' => null]);

        $this->artisan('orders:requeue-delivered-sync --name="Golden Chicken"')
            ->assertExitCode(0);

        $this->assertSame('pending_sync', $targetOrder->fresh()->autocount_sync_status);
        $this->assertSame('synced', $otherOrder->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_skips_orders_with_existing_document_refs_without_force(): void
    {
        $customer = $this->makeCustomer('Golden Chicken');
        $order = $this->makeOrder($customer, [
            'autocount_sync_status' => 'do_created',
            'api_do_id' => 'DO-0001',
            'api_invoice_id' => 'INV-0001',
        ]);

        $this->artisan('orders:requeue-delivered-sync --name="Golden Chicken"')
            ->assertExitCode(0);

        $this->assertSame('do_created', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function force_requeues_orders_with_document_refs_and_clears_them(): void
    {
        $customer = $this->makeCustomer('Golden Chicken');
        $order = $this->makeOrder($customer, [
            'autocount_sync_status' => 'paid_synced',
            'api_do_id' => 'DO-0001',
            'api_invoice_id' => 'INV-0001',
        ]);

        $this->artisan('orders:requeue-delivered-sync --name="Golden Chicken" --force')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending_sync', $order->autocount_sync_status);
        $this->assertNull($order->api_do_id);
        $this->assertNull($order->api_invoice_id);
    }

    /** @test */
    public function unmatched_names_are_reported_and_do_not_fail(): void
    {
        $customer = $this->makeCustomer('Golden Chicken');
        $this->makeOrder($customer, ['api_do_id' => null, 'api_invoice_id' => null]);

        $this->artisan('orders:requeue-delivered-sync --name="Golden Chicken" --name="No Such Customer"')
            ->expectsOutput('No customer matched for: No Such Customer')
            ->assertExitCode(0);
    }
}
