<?php

namespace Tests\Feature;

use App\Driver;
use App\Order;
use App\OrderPayment;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettleDeliveredPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriver(): Driver
    {
        return Driver::create([
            'name' => 'Ali Driver',
            'phone' => '0123456789',
            'lorry_number' => 'LRY-1',
            'username' => 'driver' . rand(1000, 9999),
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function makeCustomer(string $type): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => $type,
            'customer_type' => $type,
            'credit_balance' => 0,
            'status' => 'active',
            'payment_method' => $type === 'credit' ? 'credit-term' : 'cod',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
            'order_type' => 'registered',
            'total_price' => 100.00,
            'subtotal' => 100.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => Order::$status['delivered'],
            'fulfillment_type' => Order::$fulfillment_types['delivery'],
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function it_records_a_credit_term_payment_for_delivered_credit_orders_with_a_driver(): void
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder([
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'payment_method' => 'credit-term',
        ]);

        $this->artisan('orders:settle-delivered')->assertExitCode(0);

        $payment = OrderPayment::where('order_id', $order->id)
            ->where('payment_method', 'credit-term')
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->first();

        $this->assertNotNull($payment, 'A confirmed credit-term payment should be recorded.');
        $this->assertEqualsWithDelta(100.00, (float) $payment->amount, 0.001);
        $this->assertSame($driver->id, $payment->recorded_by_driver);
        // The credit charge posts to the customer ledger (they now owe it).
        $this->assertEqualsWithDelta(-100.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function it_puts_delivered_walk_in_orders_on_hold(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder([
            'order_type' => 'walk_in',
            'driver_id' => $driver->id,
            'walk_in_name' => 'Cash Buyer',
        ]);

        $this->artisan('orders:settle-delivered')->assertExitCode(0);

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->payment_held_at, 'payment_held_at should be set.');
        $this->assertSame($driver->id, $fresh->payment_held_by);
        $this->assertSame(Order::$payment_status['on_hold'], $fresh->payment_status);
    }

    /** @test */
    public function dry_run_writes_nothing(): void
    {
        $driver = $this->makeDriver();
        $creditOrder = $this->makeOrder([
            'user_id' => $this->makeCustomer('credit')->id,
            'driver_id' => $driver->id,
        ]);
        $walkInOrder = $this->makeOrder([
            'order_type' => 'walk_in',
            'driver_id' => $driver->id,
        ]);

        $this->artisan('orders:settle-delivered', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, OrderPayment::where('order_id', $creditOrder->id)->count());
        $this->assertNull($walkInOrder->fresh()->payment_held_at);
    }

    /** @test */
    public function it_skips_orders_without_a_driver_or_not_delivered(): void
    {
        $driver = $this->makeDriver();
        // Delivered credit order but NO driver.
        $noDriver = $this->makeOrder(['user_id' => $this->makeCustomer('credit')->id]);
        // Credit order with a driver but still in route.
        $notDelivered = $this->makeOrder([
            'user_id' => $this->makeCustomer('credit')->id,
            'driver_id' => $driver->id,
            'status' => Order::$status['in_route'],
        ]);

        $this->artisan('orders:settle-delivered')->assertExitCode(0);

        $this->assertSame(0, OrderPayment::where('order_id', $noDriver->id)->count());
        $this->assertSame(0, OrderPayment::where('order_id', $notDelivered->id)->count());
    }

    /** @test */
    public function it_is_idempotent_and_does_not_double_charge(): void
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder([
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
        ]);

        $this->artisan('orders:settle-delivered')->assertExitCode(0);
        $this->artisan('orders:settle-delivered')->assertExitCode(0);

        $this->assertSame(
            1,
            OrderPayment::where('order_id', $order->id)->where('payment_method', 'credit-term')->count(),
            'Re-running must not record a second credit-term payment.'
        );
        $this->assertEqualsWithDelta(-100.00, (float) $customer->fresh()->credit_balance, 0.001);
    }
}
