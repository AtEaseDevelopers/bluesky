<?php

namespace Tests\Feature;

use App\Admin;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\Services\CreditService;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderCancellationCreditTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeCreditCustomer(float $balance = 0): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood ' . rand(1000, 9999),
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'credit_balance' => $balance,
            'status' => 'active',
            'payment_method' => json_encode(['credit-term']),
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(User $customer, array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'delivered',
            'fulfillment_type' => 'delivery',
            'driver_id' => null,
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function cancelling_a_credit_term_order_clears_the_amount_the_customer_owed(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        // Buy-now-pay-later charge parks the order on credit; customer now owes 30.
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, $admin->id);
        $this->assertSame(Order::$status['credit'], $order->fresh()->status);
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);

        app(OrderStatusService::class)->transition($order->fresh(), Order::$status['cancelled'], $admin->id);

        // No goods delivered, so the customer no longer owes anything.
        $this->assertSame(Order::$status['cancelled'], $order->fresh()->status);
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);

        // A reversal is logged against the order and the credit-term payment is voided.
        $this->assertSame(1, CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_reversal')->count());
        $this->assertSame(0, OrderPayment::where('order_id', $order->id)
            ->where('payment_method', 'credit-term')
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->count());
    }

    /** @test */
    public function cancelling_an_order_restores_credit_that_was_auto_applied(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        app(CreditService::class)->manualAdjust($customer->fresh(), 100.00, 'Opening credit', $admin->id);

        // Order still being packed; available credit is auto-applied to it.
        $order = $this->makeOrder($customer, ['status' => 'packing']);
        $applied = app(CreditService::class)->applyAvailableCredit($order->fresh());

        $this->assertEqualsWithDelta(30.00, $applied, 0.001);
        $this->assertEqualsWithDelta(70.00, (float) $customer->fresh()->credit_balance, 0.001);

        app(OrderStatusService::class)->transition($order->fresh(), Order::$status['cancelled'], $admin->id);

        // The consumed credit is returned to the customer's balance in full.
        $this->assertEqualsWithDelta(100.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(0, OrderPayment::where('order_id', $order->id)
            ->where('payment_method', 'customer-credit')
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->count());
    }

    /** @test */
    public function cancelling_leaves_money_the_customer_already_paid_as_available_credit(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        // Credit-term charge recorded, then the customer settles it in full.
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, $admin->id);
        app(CreditService::class)->settleOrderCredit($order->fresh(), $admin->id);
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);

        app(OrderStatusService::class)->transition($order->fresh(), Order::$status['cancelled'], $admin->id);

        // The charge is reversed but the settled money survives as available credit.
        $this->assertEqualsWithDelta(30.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** A pre-fix cancelled order: charged on the ledger but never reversed. */
    private function makeLegacyCancelledCreditOrder(User $customer, float $amount = 30.00): Order
    {
        $order = $this->makeOrder($customer, ['total_price' => $amount, 'subtotal' => $amount]);
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', $amount, null, null, null);
        // Cancel directly, bypassing the transition that now auto-reverses.
        $order->update(['status' => Order::$status['cancelled']]);

        return $order->fresh();
    }

    /** @test */
    public function backfill_dry_run_reports_but_leaves_the_balance_untouched(): void
    {
        $customer = $this->makeCreditCustomer();
        $this->makeLegacyCancelledCreditOrder($customer);
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);

        $this->artisan('credit:backfill-cancelled-reversals', ['--dry-run' => true])->assertExitCode(0);

        // Still owing; nothing written.
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(0, CustomerCreditLog::where('type', 'credit_reversal')->count());
    }

    /** @test */
    public function backfill_reverses_old_cancelled_orders_and_is_idempotent(): void
    {
        $customer = $this->makeCreditCustomer();
        $order = $this->makeLegacyCancelledCreditOrder($customer);

        $this->artisan('credit:backfill-cancelled-reversals')->assertExitCode(0);

        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(1, CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_reversal')->count());

        // Running again posts no further reversal.
        $this->artisan('credit:backfill-cancelled-reversals')->assertExitCode(0);
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(1, CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_reversal')->count());
    }

    /** @test */
    public function backfill_ignores_orders_that_are_not_cancelled(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);
        // Live credit order — charged and owing, but still active.
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, $admin->id);

        $this->artisan('credit:backfill-cancelled-reversals')->assertExitCode(0);

        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(0, CustomerCreditLog::where('type', 'credit_reversal')->count());
    }

    /** @test */
    public function cancelling_a_cod_order_does_not_touch_the_credit_ledger(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer, ['status' => 'pending']);

        app(OrderStatusService::class)->transition($order->fresh(), Order::$status['cancelled'], $admin->id);

        $this->assertSame(0, CustomerCreditLog::where('order_id', $order->id)->count());
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
    }
}
