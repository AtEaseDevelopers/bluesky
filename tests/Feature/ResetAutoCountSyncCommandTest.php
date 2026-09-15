<?php

namespace Tests\Feature;

use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetAutoCountSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
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

    private function makeOrder(array $overrides = []): Order
    {
        return Order::forceCreate(array_merge([
            'user_id' => $this->makeCustomer()->id,
            'order_type' => 'registered',
            'total_price' => 20.00,
            'subtotal' => 20.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 20.00,
            'status' => Order::$status['completed'],
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
    public function dry_run_reports_eligible_orders_but_changes_nothing(): void
    {
        $order = $this->makeOrder(['autocount_sync_status' => 'sync_error']);

        $this->artisan('orders:reset-autocount-sync --dry-run')
            ->assertExitCode(0);

        // Dry run must not touch the row.
        $this->assertSame('sync_error', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_requeues_eligible_never_synced_orders(): void
    {
        $order = $this->makeOrder([
            'autocount_sync_status' => 'sync_error',
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:reset-autocount-sync')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending_sync', $order->autocount_sync_status);
        $this->assertNull($order->autocount_synced_at);
    }

    /** @test */
    public function it_leaves_already_synced_orders_untouched_by_default(): void
    {
        $order = $this->makeOrder([
            'autocount_sync_status' => 'synced',
            'api_do_id' => 'DO-0001',
            'api_invoice_id' => 'INV-0001',
        ]);

        $this->artisan('orders:reset-autocount-sync')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('synced', $order->autocount_sync_status);
        $this->assertSame('DO-0001', $order->api_do_id);
    }

    /** @test */
    public function force_requeues_synced_orders_and_clears_document_refs(): void
    {
        $order = $this->makeOrder([
            'autocount_sync_status' => 'paid_synced',
            'api_do_id' => 'DO-0001',
            'api_invoice_id' => 'INV-0001',
        ]);

        $this->artisan('orders:reset-autocount-sync --force')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending_sync', $order->autocount_sync_status);
        $this->assertNull($order->api_do_id);
        $this->assertNull($order->api_invoice_id);
        $this->assertNull($order->autocount_synced_at);
    }

    /** @test */
    public function it_ignores_orders_that_are_not_paid_and_completed(): void
    {
        $notCompleted = $this->makeOrder([
            'status' => 'pending',
            'autocount_sync_status' => 'sync_error',
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);
        $notPaid = $this->makeOrder([
            'payment_status' => 'unpaid',
            'autocount_sync_status' => 'sync_error',
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:reset-autocount-sync')->assertExitCode(0);

        $this->assertSame('sync_error', $notCompleted->fresh()->autocount_sync_status);
        $this->assertSame('sync_error', $notPaid->fresh()->autocount_sync_status);
    }

    /** @test */
    public function unqueue_sets_queued_orders_back_to_pending(): void
    {
        $order = $this->makeOrder([
            'autocount_sync_status' => 'pending_sync',
            'api_do_id' => null,
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:reset-autocount-sync --unqueue')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending', $order->autocount_sync_status);
        $this->assertNull($order->autocount_synced_at);
    }

    /** @test */
    public function unqueue_also_clears_sync_error_orders(): void
    {
        $errored = $this->makeOrder(['autocount_sync_status' => 'sync_error']);

        $this->artisan('orders:reset-autocount-sync --unqueue')->assertExitCode(0);

        $this->assertSame('pending', $errored->fresh()->autocount_sync_status);
    }

    /** @test */
    public function unqueue_dry_run_changes_nothing(): void
    {
        $order = $this->makeOrder(['autocount_sync_status' => 'pending_sync']);

        $this->artisan('orders:reset-autocount-sync --unqueue --dry-run')->assertExitCode(0);

        $this->assertSame('pending_sync', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function unqueue_leaves_orders_that_already_have_documents_untouched(): void
    {
        $synced = $this->makeOrder([
            'autocount_sync_status' => 'do_created',
            'api_do_id' => 'DO-0001',
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:reset-autocount-sync --unqueue')->assertExitCode(0);

        // Only 'pending_sync' orders are un-queued; do_created is left alone.
        $this->assertSame('do_created', $synced->fresh()->autocount_sync_status);
    }

    /** @test */
    public function id_option_restricts_scope_to_given_orders(): void
    {
        $target = $this->makeOrder(['autocount_sync_status' => 'sync_error', 'api_do_id' => null, 'api_invoice_id' => null]);
        $other = $this->makeOrder(['autocount_sync_status' => 'sync_error', 'api_do_id' => null, 'api_invoice_id' => null]);

        $this->artisan('orders:reset-autocount-sync --id=' . $target->id)->assertExitCode(0);

        $this->assertSame('pending_sync', $target->fresh()->autocount_sync_status);
        $this->assertSame('sync_error', $other->fresh()->autocount_sync_status);
    }
}
