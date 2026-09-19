<?php

namespace Tests\Feature;

use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * orders:check-autocount-midflight must flag orders that have a Delivery Order in
 * AutoCount (api_do_id set) but no invoice yet (api_invoice_id NULL) — the only
 * orders the direct Invoice/Cash Sale cutover would double-deduct — and stay
 * silent (exit 0) once none remain.
 */
class CheckAutoCountMidFlightCommandTest extends TestCase
{
    use RefreshDatabase;

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
            'payment_method' => 'cash',
            'payment_status' => Order::$payment_status['paid'],
            'autocount_sync_status' => 'pending_sync',
            'api_do_id' => null,
            'api_invoice_id' => null,
            'invoice_number' => 'INV-' . rand(10000, 99999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $overrides));
    }

    /** @test */
    public function it_reports_clean_when_no_orders_are_mid_flight(): void
    {
        // Fresh queue (no DO yet) and a fully synced order are both safe.
        $this->makeOrder(['autocount_sync_status' => 'pending_sync', 'api_do_id' => null]);
        $this->makeOrder([
            'autocount_sync_status' => 'synced',
            'api_do_id' => 'DO-0001',
            'api_invoice_id' => 'INV-0001',
        ]);

        $this->artisan('orders:check-autocount-midflight')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_flags_an_order_with_a_do_but_no_invoice(): void
    {
        $order = $this->makeOrder([
            'autocount_sync_status' => 'do_created',
            'api_do_id' => 'DO-0009',
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:check-autocount-midflight')
            ->assertExitCode(1);

        // Read-only: the command must not mutate the order.
        $this->assertSame('do_created', $order->fresh()->autocount_sync_status);
        $this->assertSame('DO-0009', $order->fresh()->api_do_id);
    }

    /** @test */
    public function it_flags_a_pending_sync_order_that_already_has_a_do(): void
    {
        // An order that wrote back its DO but is still queued is just as dangerous
        // as do_created: it has a real DO in AutoCount and no invoice yet.
        $this->makeOrder([
            'autocount_sync_status' => 'pending_sync',
            'api_do_id' => 'DO-0042',
            'api_invoice_id' => null,
        ]);

        $this->artisan('orders:check-autocount-midflight')
            ->assertExitCode(1);
    }
}
